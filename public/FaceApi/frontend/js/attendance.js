// js/attendance.js

const video     = document.getElementById('video');
const canvas    = document.getElementById('overlay');
const statusDiv = document.getElementById('status');
const whoDiv    = document.getElementById('who');
const distDiv   = document.getElementById('distance');
const flashDiv  = document.getElementById('flash');

const btnIn     = document.getElementById('btnCheckIn');
const btnOut    = document.getElementById('btnCheckOut');
const btnRetry  = document.getElementById('btnRetry');

// ====== CONFIG ======
const MODEL_URL = 'https://cdn.jsdelivr.net/gh/justadudewhohacks/face-api.js@0.22.2/weights';
// Laravel API endpoints
const KNOWN_FACES_URL  = '/api/face-embeddings';
const MARK_ATT_URL     = '/api/recognize-proxy';
// Client-side recognition uses Euclidean distance.
// Lower values = stricter matching. Typical face-api.js threshold is 0.5-0.6
// We use 0.50 to reduce false positives.
const MATCH_THRESHOLD  = 0.50;
const MARGIN_THRESHOLD = 0.08; // Minimum gap between best and second-best match
const AUTO_MODE        = true; // Automatically mark attendance when confident
const AUTO_COOLDOWN_MS = 12000; // prevent double-marks within this window
const MIN_STABLE_FRAMES = 4; // Require consistent recognition across multiple frames
// ====================

let labels = [];           // ['John Doe', ...]
let descriptors = [];      // [Float32Array(128), ...]
let bestMatch = { label: null, distance: null, margin: null };
let currentDescriptor = null; // latest Float32Array from largest face
let isMarking = false;
let lastMarkTs = 0;
let lastMarkedLabel = null;
let detectionLoopStarted = false;
let stickyNotice = null; // persistent warning until attendance is marked
let stickyClass = 'text-warning';
// Multi-frame validation: track consecutive recognitions of same person
let stableRecognition = { label: null, count: 0, totalDistance: 0 };
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

    const stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'user' }, audio: false });
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
      bestMatch = { label: null, distance: null };
      whoDiv.textContent  = '--';
      distDiv.textContent = '--';
      flash('');
      setButtonsEnabled(false);
    });
  }

  if (btnIn) btnIn.addEventListener('click', () => doMark('IN'));
  if (btnOut) btnOut.addEventListener('click', () => doMark('OUT'));
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
  // Expect: [{label, descriptor:[128 floats]}] OR {"John":[...], "Jane":[...]}
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
        .detectAllFaces(video, new faceapi.TinyFaceDetectorOptions())
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
        // Reset stable recognition when no face
        stableRecognition = { label: null, count: 0, totalDistance: 0 };
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
        const d = largest.descriptor;
        currentDescriptor = d; // keep for server marking

        const { label, distance, margin } = findBestMatch(d);
        bestMatch = { label, distance, margin };

        // Check if match passes both threshold AND margin requirements
        const passesThreshold = label && distance <= MATCH_THRESHOLD;
        const passesMargin = margin === null || margin >= MARGIN_THRESHOLD;
        const isConfidentMatch = passesThreshold && passesMargin;

        // IMPORTANT: Only show the matched name if confidence is HIGH enough
        // This prevents confusing users when an unknown face shows a registered person's name
        if (isConfidentMatch) {
          whoDiv.textContent = label;
          distDiv.textContent = distance.toFixed(4);
        } else {
          // Show "Unknown" for low-confidence matches to avoid confusion
          whoDiv.textContent = 'Unknown';
          // Still show distance for debugging, but indicate it's not confident
          distDiv.textContent = distance != null ? `${distance.toFixed(4)} (too high)` : '--';
        }

        // Multi-frame validation: track consecutive recognitions
        if (isConfidentMatch && label === stableRecognition.label) {
          stableRecognition.count++;
          stableRecognition.totalDistance += distance;
        } else if (isConfidentMatch) {
          // New confident match, reset counter
          stableRecognition = { label, count: 1, totalDistance: distance };
        } else {
          // Not confident, reset
          stableRecognition = { label: null, count: 0, totalDistance: 0 };
        }

        const isStableMatch = stableRecognition.count >= MIN_STABLE_FRAMES;
        const avgDistance = isStableMatch ? (stableRecognition.totalDistance / stableRecognition.count) : null;

        if (isStableMatch) {
          ok(`Ready: ${label} (avg dist ${avgDistance.toFixed(4)}, ${stableRecognition.count} frames)`);
          setButtonsEnabled(true);

          if (AUTO_MODE) {
            const now = Date.now();
            const onCooldown = now - lastMarkTs < AUTO_COOLDOWN_MS;
            const sameAsLast = lastMarkedLabel && lastMarkedLabel === label;
            if (!isMarking && (!onCooldown || !sameAsLast)) {
              isMarking = true;
              doMark('AUTO').finally(() => {
                isMarking = false;
                lastMarkTs = Date.now();
                lastMarkedLabel = label;
                // Reset stable recognition after marking
                stableRecognition = { label: null, count: 0, totalDistance: 0 };
              });
            }
          }
        } else if (isConfidentMatch) {
          // Building confidence, show progress
          status(`Verifying: ${label} (${stableRecognition.count}/${MIN_STABLE_FRAMES} frames)`);
          setButtonsEnabled(false);
        } else if (passesThreshold && !passesMargin) {
          // Ambiguous match - could be multiple people
          status('Face similar to multiple people. Please adjust position.');
          setSticky('Recognition ambiguous. Move closer or adjust lighting.', 'text-warning');
          setButtonsEnabled(false);
        } else {
          // Unknown face or low confidence - clearly indicate this
          status('Unknown face detected.');
          setSticky('This face is not registered in the system.', 'text-danger');
          setButtonsEnabled(false);

          // DO NOT send unrecognized faces to server in AUTO mode
          // This prevents false positives from unknown faces
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

function findBestMatch(queryDescr /* Float32Array */) {
  if (!descriptors.length) return { label: null, distance: null, margin: null };

  let bestIdx = -1;
  let bestDist = Infinity;
  let secondBestDist = Infinity;
  
  for (let i = 0; i < descriptors.length; i++) {
    const dist = faceapi.euclideanDistance(queryDescr, descriptors[i]);
    if (dist < bestDist) {
      secondBestDist = bestDist;
      bestDist = dist;
      bestIdx = i;
    } else if (dist < secondBestDist) {
      secondBestDist = dist;
    }
  }
  
  const label = (bestIdx >= 0) ? labels[bestIdx] : null;
  // Margin: how much better is the best match vs second-best
  // Higher margin = more confident the match is correct
  const margin = secondBestDist - bestDist;
  
  return { label, distance: bestDist, margin };
}

async function doMark(action /* 'IN' | 'OUT' | 'AUTO' */) {
  try {
    // Strict validation: reject if no confident match
    if (!bestMatch.label) {
      if (action !== 'AUTO') {
        flash('No recognized face yet. Try facing the camera and click Retry.', 'text-danger');
      }
      return;
    }
    
    // Check threshold
    if (bestMatch.distance == null || bestMatch.distance > MATCH_THRESHOLD) {
      if (action !== 'AUTO') {
        flash(`Recognition not confident (distance ${bestMatch.distance?.toFixed(4) ?? '--'}).`, 'text-danger');
      }
      return;
    }
    
    // Check margin - reject ambiguous matches
    if (bestMatch.margin !== null && bestMatch.margin < MARGIN_THRESHOLD) {
      if (action !== 'AUTO') {
        flash('Match is ambiguous. Please adjust your position.', 'text-danger');
      }
      return;
    }
    
    // For AUTO mode, require stable multi-frame recognition
    if (action === 'AUTO' && stableRecognition.count < MIN_STABLE_FRAMES) {
      return; // Still building confidence, don't mark yet
    }

    // Get CSRF token
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
    
    // Create a canvas to capture the current face
    const canvas = document.createElement('canvas');
    const ctx = canvas.getContext('2d');
    canvas.width = 640;
    canvas.height = 480;
    
    // Draw the current video frame
    ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
    
    // Convert canvas to blob
    const blob = await new Promise(resolve => canvas.toBlob(resolve, 'image/jpeg', 0.8));
    
    // Create form data for Laravel API
    const formData = new FormData();
    if (action === 'AUTO') {
      formData.append('action', 'auto');
    } else {
      formData.append('action', action === 'IN' ? 'time_in' : 'time_out');
    }
    formData.append('image', blob, 'face.jpg');
    formData.append('device_id', 1); // Default device ID
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