// js/attendance.js - Improved with liveness detection and better accuracy

const video     = document.getElementById('video');
const canvas    = document.getElementById('overlay');
const statusDiv = document.getElementById('status');
const whoDiv    = document.getElementById('who');
const distDiv   = document.getElementById('distance');
const flashDiv  = document.getElementById('flash');

const btnIn     = document.getElementById('btnCheckIn');
const btnOut    = document.getElementById('btnCheckOut');
const btnRetry  = document.getElementById('btnRetry');

// ====== IMPROVED CONFIG ======
const MODEL_URL = 'https://cdn.jsdelivr.net/gh/justadudewhohacks/face-api.js@0.22.2/weights';
// Laravel API endpoints
const KNOWN_FACES_URL  = '/api/face-embeddings';
const MARK_ATT_URL     = '/api/recognize-proxy';

// IMPROVED: Much stricter thresholds for better accuracy
// Client-side uses Euclidean distance (lower is better, 0 = perfect match)
// Typical good matches: 0.3-0.4, marginal: 0.4-0.5, unknown: 0.5+
const MATCH_THRESHOLD  = 0.42;   // Very strict - reject anything above this
const HIGH_CONFIDENCE  = 0.35;   // Very confident match threshold
const SECONDARY_GAP    = 0.10;   // Require 10% gap between best and second-best match
const MIN_MATCHES_REQ  = 1;      // Minimum templates that must match well
const AUTO_MODE        = true; 
const AUTO_COOLDOWN_MS = 12000;

// IMPROVED: Stricter face quality requirements
const MIN_FACE_SIZE    = 100;    // Minimum face width in pixels (larger = better accuracy)
const MIN_CONFIDENCE   = 0.85;   // Minimum detection confidence score
const REQUIRE_CENTERED = true;   // Face must be roughly centered

// IMPROVED: Liveness detection settings
const LIVENESS_ENABLED = true;
const BLINK_DETECTION  = true;
const MOVEMENT_CHECK   = true;
const MOVEMENT_FRAMES  = 10;     // Number of frames to check for movement
const REQUIRE_LIVENESS = true;   // Must pass liveness before allowing match
// ====================

let labels = [];           // ['John Doe', ...]
let descriptors = [];      // [Float32Array(128), ...]
let employeeIds = [];      // Store employee IDs for each face
let bestMatch = { label: null, distance: null, employeeId: null, isConfident: false };
let currentDescriptor = null; // latest Float32Array from largest face
let isMarking = false;
let lastMarkTs = 0;
let lastMarkedLabel = null;
let detectionLoopStarted = false;
let stickyNotice = null; // persistent warning until attendance is marked
let stickyClass = 'text-warning';

// Liveness detection state
let faceHistory = [];      // Store recent face positions for movement detection
let blinkState = { eyesClosed: false, blinkCount: 0, lastBlinkTime: 0 };
let livenessScore = 0;
let livenessChecks = { movement: false, blink: false, faceQuality: false };

const soundTimeIn = new Audio('/FaceApi/frontend/sounds/time_in.wav');
const soundTimeOut = new Audio('/FaceApi/frontend/sounds/time_out.wav');
soundTimeIn.preload = 'auto';
soundTimeOut.preload = 'auto';
let audioUnlockBound = false;
let lastTone = { label: null, action: null, ts: 0 };
const TONE_COOLDOWN_MS = 12000;

setButtonsEnabled(false);

(async function boot() {
  try {
    if (typeof faceapi === 'undefined') {
      throw new Error('face-api.js not loaded. Ensure the face-api <script> is before this file and both use "defer".');
    }

    status('Loading face models...');
    await Promise.all([
      faceapi.nets.tinyFaceDetector.loadFromUri(MODEL_URL),
      faceapi.nets.faceLandmark68Net.loadFromUri(MODEL_URL),
      faceapi.nets.faceRecognitionNet.loadFromUri(MODEL_URL),
      // Additional model for liveness detection (expressions help detect real faces)
      faceapi.nets.faceExpressionNet.loadFromUri(MODEL_URL),
    ]);

    // If the model URL 404’d and got cached, this is a common failure point.
    if (!faceapi.nets.tinyFaceDetector.params) {
      throw new Error(
        'TinyFaceDetector weights not loaded. Hard refresh (Ctrl+Shift+R) and check Network tab for 404s to ' +
        MODEL_URL + '/tiny_face_detector_model-*.'
      );
    }

    status('Models loaded. Loading known faces...');
    await loadKnownFaces();
    status('Known faces loaded. Starting camera...');

    if (!navigator.mediaDevices?.getUserMedia) {
      throw new Error('Camera access is not supported in this browser.');
    }

    // Request higher resolution for better accuracy
    const stream = await navigator.mediaDevices.getUserMedia({ 
      video: { 
        facingMode: 'user',
        width: { ideal: 1280 },
        height: { ideal: 720 }
      }, 
      audio: false 
    });
    video.srcObject = stream;
    await ensureVideoReady();
    startDetectionLoop();
    ok('Camera ready. Position your face inside the frame.');

    attachHandlers();
    setupAudioUnlock();
  } catch (err) {
    error(err.message || String(err));
  }
})();

function attachHandlers() {
  if (btnRetry) {
    btnRetry.addEventListener('click', () => {
      bestMatch = { label: null, distance: null, employeeId: null, isConfident: false };
      whoDiv.textContent  = '--';
      distDiv.textContent = '--';
      flash('');
      resetLivenessState();
      setButtonsEnabled(false);
    });
  }

  if (btnIn) btnIn.addEventListener('click', () => doMark('IN'));
  if (btnOut) btnOut.addEventListener('click', () => doMark('OUT'));
}

function resetLivenessState() {
  faceHistory = [];
  blinkState = { eyesClosed: false, blinkCount: 0, lastBlinkTime: 0 };
  livenessScore = 0;
  livenessChecks = { movement: false, blink: false, faceQuality: false };
}

function setButtonsEnabled(enabled) {
  if (btnIn)  btnIn.disabled  = !enabled;
  if (btnOut) btnOut.disabled = !enabled;
}

function status(msg) {
  statusDiv.className = 'text-info small';
  statusDiv.textContent = msg;
}
function ok(msg) {
  statusDiv.className = 'text-success small';
  statusDiv.textContent = msg;
}
function error(msg) {
  statusDiv.className = 'text-danger small';
  statusDiv.textContent = msg;
}
function flash(msg, cls = 'text-secondary') {
  // Keep sticky notice unless we explicitly clear it or it's a success
  if (stickyNotice && cls !== 'text-success') {
    flashDiv.className = `small ${stickyClass}`;
    flashDiv.textContent = stickyNotice;
    return;
  }
  flashDiv.className = `small ${cls}`;
  flashDiv.textContent = msg;
}

function setSticky(msg, cls = 'text-warning') {
  stickyNotice = msg;
  stickyClass = cls;
  flashDiv.className = `small ${stickyClass}`;
  flashDiv.textContent = stickyNotice;
}

function setupAudioUnlock() {
  if (audioUnlockBound) {
    return;
  }

  const once = () => {
    [soundTimeIn, soundTimeOut].forEach((audio) => {
      if (typeof audio.play !== 'function') {
        return;
      }
      const attempt = audio.play();
      if (attempt && typeof attempt.catch === 'function') {
        attempt.catch(() => {});
      }
      audio.pause();
      audio.currentTime = 0;
    });
    document.removeEventListener('click', once);
    document.removeEventListener('touchstart', once);
  };

  document.addEventListener('click', once, { passive: true });
  document.addEventListener('touchstart', once, { passive: true });
  audioUnlockBound = true;
}

function playAttendanceTone(kind /* 'time_in' | 'time_out' */) {
  const audio = kind === 'time_out' ? soundTimeOut : soundTimeIn;
  if (!audio) {
    return;
  }
  audio.pause();
  audio.currentTime = 0;
  audio.play().catch(() => {});
}

function resolveToneAction(serverAction, requestedAction) {
  const normalized = (serverAction || '').toLowerCase();
  if (normalized === 'time_in' || normalized === 'time_out') {
    return normalized;
  }
  if (requestedAction === 'IN') {
    return 'time_in';
  }
  if (requestedAction === 'OUT') {
    return 'time_out';
  }
  // For AUTO, only play if the server explicitly says time_in/time_out
  return null;
}
function clearSticky() {
  stickyNotice = null;
}

async function getJson(url, options) {
  const res = await fetch(url, options);
  const txt = await res.text();
  let data;
  try { data = JSON.parse(txt); }
  catch {
    throw new Error(`Non-JSON from ${url} (status ${res.status}): ${txt.slice(0,200)}`);
  }
  if (!res.ok) {
    const msg = (data && data.message) ? data.message : `HTTP ${res.status}`;
    throw new Error(`${url} failed: ${msg}`);
  }
  return data;
}

async function loadKnownFaces() {
  // Expect: [{label, descriptor:[128 floats], employee_id}] OR {"John":[...], "Jane":[...]}
  const data = await getJson(KNOWN_FACES_URL, { headers: { 'Accept': 'application/json' } });

  console.log('Loaded face data:', data);

  let items = [];
  if (Array.isArray(data)) {
    items = data;
  } else if (data && typeof data === 'object') {
    items = Object.keys(data).map(k => ({ label: k, descriptor: data[k] }));
  }

  labels = [];
  descriptors = [];
  employeeIds = [];

  for (const item of items) {
    if (!item || !item.label || !Array.isArray(item.descriptor)) {
      console.warn('Invalid face item:', item);
      continue;
    }
    const f32 = new Float32Array(item.descriptor);
    if (f32.length !== 128) {
      console.warn('Invalid descriptor length:', f32.length, 'for', item.label);
      continue; // sanity
    }
    labels.push(item.label);
    descriptors.push(f32);
    employeeIds.push(item.employee_id || null);
  }

  console.log(`Loaded ${labels.length} face templates:`, labels);

  if (!labels.length) {
    throw new Error('No known faces found. Please register some faces first.');
  }
}

async function ensureVideoReady() {
  try {
    await video.play();
  } catch (e) {
    throw new Error('Unable to start camera playback. Check browser permissions.');
  }
  if (!video.videoWidth || !video.videoHeight) {
    await new Promise((resolve, reject) => {
      const onLoaded = () => {
        video.removeEventListener('loadedmetadata', onLoaded);
        resolve();
      };
      const onError = (evt) => {
        video.removeEventListener('error', onError);
        reject(evt instanceof ErrorEvent ? evt.error : evt);
      };
      video.addEventListener('loadedmetadata', onLoaded, { once: true });
      video.addEventListener('error', onError, { once: true });
    });
  }
}

function startDetectionLoop() {
  if (detectionLoopStarted) return;
  detectionLoopStarted = true;

  const displaySize = {
    width: video.videoWidth || video.width,
    height: video.videoHeight || video.height,
  };

  video.width = displaySize.width;
  video.height = displaySize.height;
  canvas.width = displaySize.width;
  canvas.height = displaySize.height;
  faceapi.matchDimensions(canvas, displaySize);

  const tick = async () => {
    try {
      const dets = await faceapi
        .detectAllFaces(video, new faceapi.TinyFaceDetectorOptions({
          inputSize: 416,      // Higher resolution for better accuracy
          scoreThreshold: 0.5  // Filter out low-confidence detections
        }))
        .withFaceLandmarks()
        .withFaceDescriptors();

      const resized = faceapi.resizeResults(dets, displaySize);
      const ctx = canvas.getContext('2d');
      ctx.clearRect(0, 0, canvas.width, canvas.height);

      faceapi.draw.drawDetections(canvas, resized);
      faceapi.draw.drawFaceLandmarks(canvas, resized);

      if (resized.length === 0) {
        status('No face detected.');
        setSticky('No face detected. Please face the camera clearly.', 'text-danger');
        whoDiv.textContent  = '--';
        distDiv.textContent = '--';
        setButtonsEnabled(false);
        resetLivenessState();
      } else {
        if (resized.length > 1) {
          flash(`Multiple faces detected (${resized.length}). Using the largest face.`, 'text-warning');
        } else {
          flash('');
        }

        // Use the largest face (by box area)
        const largest = resized.reduce((a, b) => {
          const areaA = a.detection.box.width * a.detection.box.height;
          const areaB = b.detection.box.width * b.detection.box.height;
          return areaB > areaA ? b : a;
        });

        // IMPROVED: Check face quality
        const quality = checkFaceQuality(largest, displaySize);
        livenessChecks.faceQuality = quality.isValid;
        
        if (!quality.isValid) {
          status(quality.issues.join('. '));
          setSticky(quality.issues.join('. '), 'text-warning');
          setButtonsEnabled(false);
          requestAnimationFrame(tick);
          return;
        }
        
        // IMPROVED: Update liveness checks
        if (LIVENESS_ENABLED) {
          updateMovementHistory(largest);
          checkBlink(largest.landmarks);
        }

        const d = largest.descriptor;
        currentDescriptor = d; // keep for server marking

        // IMPROVED: Find best match with secondary verification
        const matchResult = findBestMatchImproved(d);
        bestMatch = matchResult;

        whoDiv.textContent  = matchResult.label || 'Unknown';
        distDiv.textContent = (matchResult.distance != null) ? matchResult.distance.toFixed(4) : '--';

        // IMPROVED: Check if match is confident enough
        if (matchResult.isConfident) {
          const livenessOk = !LIVENESS_ENABLED || calculateLivenessScore() >= 0.5;
          
          if (!livenessOk) {
            status('Verifying you are a real person... Please blink naturally.');
            setSticky('Liveness check in progress. Please blink and move slightly.', 'text-info');
            setButtonsEnabled(false);
          } else {
            ok(`Ready: ${matchResult.label} (distance ${matchResult.distance.toFixed(4)})`);
            clearSticky();
            setButtonsEnabled(true);

            if (AUTO_MODE) {
              const now = Date.now();
              const onCooldown = now - lastMarkTs < AUTO_COOLDOWN_MS;
              const sameAsLast = lastMarkedLabel && lastMarkedLabel === matchResult.label;
              if (!isMarking && (!onCooldown || !sameAsLast)) {
                isMarking = true;
                doMark('AUTO').finally(() => {
                  isMarking = false;
                  lastMarkTs = Date.now();
                  lastMarkedLabel = matchResult.label;
                  resetLivenessState();
                });
              }
            }
          }
        } else {
          // IMPROVED: Show specific rejection reasons for debugging
          const reasons = {
            'above_threshold': `Face detected but not recognized (distance: ${matchResult.distance?.toFixed(3)})`,
            'ambiguous': 'Multiple possible matches detected. Please face the camera directly.',
            'ambiguous_high': 'Match too close to other faces. Please try again.',
            'multiple_close_matches': 'Face matches multiple people - likely unknown. Please register first.',
            'not_confident_enough': 'Low confidence match. Move closer and ensure good lighting.',
          };
          const msg = reasons[matchResult.reason] || 'Face not recognized or low confidence.';
          status(msg);
          setSticky(msg, 'text-warning');
          
          // Log debug info to console
          console.log('Match rejected:', matchResult);
          
          setButtonsEnabled(false);
          // REMOVED: Fallback auto-recognition that was causing false positives
        }
      }
    } catch (e) {
      console.warn(e);
    } finally {
      requestAnimationFrame(tick);
    }
  };

  requestAnimationFrame(tick);
}

// IMPROVED: Check face quality
function checkFaceQuality(detection, displaySize) {
  const box = detection.detection.box;
  const issues = [];
  
  // Check face size
  if (box.width < MIN_FACE_SIZE || box.height < MIN_FACE_SIZE) {
    issues.push('Face too small - move closer');
  }
  
  // Check detection confidence
  if (detection.detection.score < MIN_CONFIDENCE) {
    issues.push('Low detection confidence');
  }
  
  // Check if face is centered (within middle 60% of frame)
  if (REQUIRE_CENTERED) {
    const centerX = box.x + box.width / 2;
    const centerY = box.y + box.height / 2;
    const marginX = displaySize.width * 0.2;
    const marginY = displaySize.height * 0.2;
    
    if (centerX < marginX || centerX > displaySize.width - marginX ||
        centerY < marginY || centerY > displaySize.height - marginY) {
      issues.push('Center your face in the frame');
    }
  }
  
  return {
    isValid: issues.length === 0,
    issues: issues,
    score: detection.detection.score,
    size: Math.min(box.width, box.height)
  };
}

// IMPROVED: Liveness detection through movement
function updateMovementHistory(detection) {
  const box = detection.detection.box;
  const center = { 
    x: box.x + box.width / 2, 
    y: box.y + box.height / 2,
    timestamp: Date.now()
  };
  
  faceHistory.push(center);
  
  // Keep only recent frames
  while (faceHistory.length > MOVEMENT_FRAMES) {
    faceHistory.shift();
  }
  
  // Check for natural movement (not a static photo)
  if (faceHistory.length >= MOVEMENT_FRAMES) {
    let totalMovement = 0;
    for (let i = 1; i < faceHistory.length; i++) {
      const dx = faceHistory[i].x - faceHistory[i-1].x;
      const dy = faceHistory[i].y - faceHistory[i-1].y;
      totalMovement += Math.sqrt(dx*dx + dy*dy);
    }
    
    // Real faces have natural micro-movements (1-10 pixels)
    // Photos are completely static or move uniformly
    const avgMovement = totalMovement / (faceHistory.length - 1);
    const hasNaturalMovement = avgMovement > 0.5 && avgMovement < 15;
    
    livenessChecks.movement = hasNaturalMovement;
  }
}

// IMPROVED: Blink detection using eye landmarks
function checkBlink(landmarks) {
  if (!landmarks || !BLINK_DETECTION) return;
  
  // Eye landmarks: left eye (36-41), right eye (42-47)
  const leftEye = landmarks.getLeftEye();
  const rightEye = landmarks.getRightEye();
  
  if (!leftEye || !rightEye) return;
  
  // Calculate Eye Aspect Ratio (EAR)
  const leftEAR = calculateEAR(leftEye);
  const rightEAR = calculateEAR(rightEye);
  const avgEAR = (leftEAR + rightEAR) / 2;
  
  // Blink threshold
  const BLINK_THRESHOLD = 0.21;
  
  if (avgEAR < BLINK_THRESHOLD) {
    if (!blinkState.eyesClosed) {
      blinkState.eyesClosed = true;
    }
  } else {
    if (blinkState.eyesClosed) {
      // Eyes just opened - count as a blink
      blinkState.blinkCount++;
      blinkState.lastBlinkTime = Date.now();
      blinkState.eyesClosed = false;
    }
  }
  
  // Consider blink check passed if we detected at least 1 blink in last 10 seconds
  const recentBlink = (Date.now() - blinkState.lastBlinkTime) < 10000;
  livenessChecks.blink = blinkState.blinkCount > 0 && recentBlink;
}

function calculateEAR(eye) {
  // Eye Aspect Ratio formula
  // EAR = (||p2-p6|| + ||p3-p5||) / (2 * ||p1-p4||)
  const p1 = eye[0], p2 = eye[1], p3 = eye[2];
  const p4 = eye[3], p5 = eye[4], p6 = eye[5];
  
  const vertical1 = Math.sqrt(Math.pow(p2.x - p6.x, 2) + Math.pow(p2.y - p6.y, 2));
  const vertical2 = Math.sqrt(Math.pow(p3.x - p5.x, 2) + Math.pow(p3.y - p5.y, 2));
  const horizontal = Math.sqrt(Math.pow(p1.x - p4.x, 2) + Math.pow(p1.y - p4.y, 2));
  
  if (horizontal === 0) return 0.3; // Avoid division by zero
  return (vertical1 + vertical2) / (2.0 * horizontal);
}

// IMPROVED: Calculate overall liveness score
function calculateLivenessScore() {
  let score = 0;
  let checks = 0;
  
  if (MOVEMENT_CHECK) {
    checks++;
    if (livenessChecks.movement) score++;
  }
  
  if (BLINK_DETECTION) {
    checks++;
    if (livenessChecks.blink) score++;
  }
  
  if (livenessChecks.faceQuality) {
    checks++;
    score++;
  }
  
  return checks > 0 ? score / checks : 0;
}

// IMPROVED: Much stricter matching with multiple validation checks
function findBestMatchImproved(queryDescr) {
  if (!descriptors.length) {
    return { label: null, distance: null, employeeId: null, isConfident: false, reason: 'no_templates' };
  }

  // Calculate distances to all templates
  const matches = [];
  for (let i = 0; i < descriptors.length; i++) {
    const dist = faceapi.euclideanDistance(queryDescr, descriptors[i]);
    matches.push({
      index: i,
      label: labels[i],
      employeeId: employeeIds[i],
      distance: dist
    });
  }
  
  // Sort by distance (lower is better)
  matches.sort((a, b) => a.distance - b.distance);
  
  const best = matches[0];
  const secondBest = matches.length > 1 ? matches[1] : null;
  const thirdBest = matches.length > 2 ? matches[2] : null;
  
  // Log for debugging
  console.log('Match candidates:', {
    best: { label: best.label, distance: best.distance.toFixed(4) },
    secondBest: secondBest ? { label: secondBest.label, distance: secondBest.distance.toFixed(4) } : null,
    gap: secondBest ? (secondBest.distance - best.distance).toFixed(4) : 'N/A'
  });
  
  let isConfident = false;
  let reason = null;
  
  // STRICT CHECK 1: Best distance must be below threshold
  if (best.distance > MATCH_THRESHOLD) {
    reason = 'above_threshold';
    console.log(`REJECTED: Distance ${best.distance.toFixed(4)} > threshold ${MATCH_THRESHOLD}`);
  } 
  // STRICT CHECK 2: Very high confidence (distance < 0.30) - always accept
  else if (best.distance < 0.30) {
    isConfident = true;
    reason = 'very_high_confidence';
    console.log(`ACCEPTED: Very high confidence, distance ${best.distance.toFixed(4)}`);
  }
  // STRICT CHECK 3: High confidence (distance < HIGH_CONFIDENCE)
  else if (best.distance < HIGH_CONFIDENCE) {
    // Still require some gap from second-best
    if (!secondBest || (secondBest.distance - best.distance) >= 0.05) {
      isConfident = true;
      reason = 'high_confidence';
      console.log(`ACCEPTED: High confidence with gap`);
    } else {
      reason = 'ambiguous_high';
      console.log(`REJECTED: High score but too close to second-best`);
    }
  }
  // STRICT CHECK 4: Medium confidence - require significant gap
  else if (best.distance <= MATCH_THRESHOLD) {
    if (!secondBest) {
      // Only one template - need very good distance
      if (best.distance < 0.40) {
        isConfident = true;
        reason = 'confident_single';
      } else {
        reason = 'not_confident_enough';
      }
    } else {
      const gap = secondBest.distance - best.distance;
      
      // CRITICAL: Unknown faces score similarly against everyone
      // Genuine matches have clear separation
      if (gap < SECONDARY_GAP) {
        reason = 'ambiguous';
        console.log(`REJECTED: Ambiguous - gap ${gap.toFixed(4)} < required ${SECONDARY_GAP}`);
      } 
      // Check if multiple people have similar high scores (sign of unknown face)
      else if (secondBest.distance < 0.55) {
        reason = 'multiple_close_matches';
        console.log(`REJECTED: Second-best also has good score ${secondBest.distance.toFixed(4)}`);
      }
      else {
        isConfident = true;
        reason = 'confident';
        console.log(`ACCEPTED: Confident with gap ${gap.toFixed(4)}`);
      }
    }
  }
  
  return {
    label: best.label,
    distance: best.distance,
    employeeId: best.employeeId,
    isConfident: isConfident,
    reason: reason,
    secondBestDistance: secondBest?.distance || null,
    gap: secondBest ? (secondBest.distance - best.distance) : null
  };
}

function findBestMatch(queryDescr /* Float32Array */) {
  if (!descriptors.length) return { label: null, distance: null };

  let bestIdx = -1;
  let bestDist = Infinity;
  for (let i = 0; i < descriptors.length; i++) {
    const dist = faceapi.euclideanDistance(queryDescr, descriptors[i]);
    if (dist < bestDist) {
      bestDist = dist;
      bestIdx = i;
    }
  }
  const label = (bestIdx >= 0) ? labels[bestIdx] : null;
  return { label, distance: bestDist };
}

async function doMark(action /* 'IN' | 'OUT' | 'AUTO' */) {
  try {
    if (!bestMatch.label && action !== 'AUTO') {
      flash('No recognized face yet. Try facing the camera and click Retry.', 'text-danger');
      return;
    }
    if (action !== 'AUTO') {
      if (!bestMatch.isConfident) {
        flash(`Recognition not confident enough.`, 'text-danger');
        return;
      }
    }

    // Get CSRF token
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
    
    // Create a canvas to capture the current face
    const canvasEl = document.createElement('canvas');
    const ctx = canvasEl.getContext('2d');
    canvasEl.width = 640;
    canvasEl.height = 480;
    
    // Draw the current video frame
    ctx.drawImage(video, 0, 0, canvasEl.width, canvasEl.height);
    
    // Convert canvas to blob
    const blob = await new Promise(resolve => canvasEl.toBlob(resolve, 'image/jpeg', 0.8));
    
    // Create form data for Laravel API
    const formData = new FormData();
    if (action === 'AUTO') {
      formData.append('action', 'auto');
    } else {
      formData.append('action', action === 'IN' ? 'time_in' : 'time_out');
    }
    formData.append('image', blob, 'face.jpg');
    formData.append('device_id', 1); // Default device ID
    
    // Send liveness status
    const livenessPass = LIVENESS_ENABLED ? calculateLivenessScore() >= 0.5 : true;
    formData.append('liveness_pass', livenessPass ? '1' : '0');
    
    if (currentDescriptor) {
      try { formData.append('embedding', JSON.stringify(Array.from(currentDescriptor))); } catch {}
    }
    
    const headers = {};
    if (csrfToken) {
      headers['X-CSRF-TOKEN'] = csrfToken;
    }

    const res = await fetch(MARK_ATT_URL, {
      method: 'POST',
      headers: { ...headers, 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      body: formData
    });

    const text = await res.text();
    let data;
    try { data = text ? JSON.parse(text) : {}; } catch (e) {
      setSticky(`Server error (${res.status}). ${text?.slice(0,200)}`, 'text-danger');
      throw new Error(`Non-JSON from server (${res.status}): ${text?.slice(0,200)}`);
    }

    if (!res.ok) {
      throw new Error(data.message || `Failed to mark ${action}`);
    }
    const act = (data.action || '').toString().toLowerCase() || (action === 'IN' ? 'time_in' : action === 'OUT' ? 'time_out' : 'auto');
    clearSticky();
    flash(`Marked ${act.replace('_',' ')} for ${bestMatch.label} (confidence ${data.confidence?.toFixed(4) || 'N/A'})`, 'text-success');
    const toneAction = resolveToneAction(act, action);
    // Only play audio when the server confirms a time_in / time_out, and avoid repeats for same person/action within cooldown
    const nowTs = Date.now();
    const labelForTone = bestMatch.label || data.employee_name || 'unknown';
    const onToneCooldown = lastTone.label === labelForTone && lastTone.action === toneAction && (nowTs - lastTone.ts < TONE_COOLDOWN_MS);
    if (res.ok && (toneAction === 'time_in' || toneAction === 'time_out') && !onToneCooldown) {
      playAttendanceTone(toneAction);
      lastTone = { label: labelForTone, action: toneAction, ts: nowTs };
    }
  } catch (err) {
    console.error(err);
    setSticky(err.message || String(err), 'text-danger');
  }
}
