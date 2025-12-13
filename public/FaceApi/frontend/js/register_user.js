// js/register_user.js

const video = document.getElementById('video');
const canvas = document.getElementById('overlay');
const statusDiv = document.getElementById('status');

let capturedDescriptor = null;
let detectionLoopStarted = false;

// Use GitHub CDN for weights (NPM CDN doesn't host weights)
const MODEL_URL = 'https://cdn.jsdelivr.net/gh/justadudewhohacks/face-api.js@0.22.2/weights';

// Laravel API endpoint
const API_URL = '/api/register-face';

(async function boot() {
  try {
    if (typeof faceapi === 'undefined') {
      throw new Error('face-api.js not loaded. Ensure script order: face-api first, then this file (both with defer).');
    }

    // Start loading models in the background
    let modelsReady = false;
    const modelsPromise = (async () => {
      statusDiv.textContent = 'Loading face models...';
      await Promise.all([
        faceapi.nets.tinyFaceDetector.loadFromUri(MODEL_URL),
        faceapi.nets.faceLandmark68Net.loadFromUri(MODEL_URL),
        faceapi.nets.faceRecognitionNet.loadFromUri(MODEL_URL),
      ]);
      if (!faceapi.nets.tinyFaceDetector.params) {
        throw new Error('TinyFaceDetector weights not loaded (check Network tab for 404 on manifest/bin).');
      }
      modelsReady = true;
    })();

    // Start camera immediately so user sees preview
    if (!navigator.mediaDevices?.getUserMedia) {
      throw new Error('Camera access is not supported in this browser.');
    }
    const stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'user' }, audio: false });
    video.srcObject = stream;
    await ensureVideoReady();
    statusDiv.textContent = 'Camera ready. Finalizing model load...';
    statusDiv.className = 'text-info';

    // Wait for models, then start detection
    await modelsPromise;
    if (modelsReady) {
      startDetectionLoop();
      statusDiv.textContent = 'Camera ready. You can capture your face.';
      statusDiv.className = 'text-success';
    }
  } catch (err) {
    console.error(err);
    statusDiv.textContent = err.message || String(err);
    statusDiv.className = 'text-danger';
  }
})();

async function ensureVideoReady() {
  if (!video.srcObject) return;
  try {
    await video.play();
  } catch (e) {
    throw new Error('Unable to start the camera stream. Check browser permissions.');
  }
  if (!video.videoWidth || !video.videoHeight) {
    await new Promise((resolve, reject) => {
      const onLoaded = () => {
        video.removeEventListener('loadedmetadata', onLoaded);
        resolve();
      };
      const onError = (e) => {
        video.removeEventListener('error', onError);
        reject(e instanceof ErrorEvent ? e.error : e);
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
      const detections = await faceapi
        .detectAllFaces(video, new faceapi.TinyFaceDetectorOptions())
        .withFaceLandmarks()
        .withFaceDescriptors();

      const resized = faceapi.resizeResults(detections, displaySize);
      const ctx = canvas.getContext('2d');
      ctx.clearRect(0, 0, canvas.width, canvas.height);
      faceapi.draw.drawDetections(canvas, resized);
      faceapi.draw.drawFaceLandmarks(canvas, resized);
    } catch (e) {
      console.warn(e);
    } finally {
      requestAnimationFrame(tick);
    }
  };

  requestAnimationFrame(tick);
}

document.getElementById('captureBtn').addEventListener('click', async () => {
  try {
    if (!faceapi?.nets?.tinyFaceDetector?.params) {
      statusDiv.textContent = 'Models are still loading. Please wait a moment...';
      statusDiv.className = 'text-warning';
      return;
    }
    const det = await faceapi
      .detectSingleFace(video, new faceapi.TinyFaceDetectorOptions())
      .withFaceLandmarks()
      .withFaceDescriptor();

    if (!det) {
      statusDiv.textContent = 'No face detected. Try again.';
      statusDiv.className = 'text-danger';
      return;
    }

    capturedDescriptor = Array.from(det.descriptor);
    statusDiv.textContent = 'Face captured successfully!';
    statusDiv.className = 'text-success';
  } catch (e) {
    console.error(e);
    statusDiv.textContent = e.message || String(e);
    statusDiv.className = 'text-danger';
  }
});

document.getElementById('registrationForm').addEventListener('submit', async (e) => {
  e.preventDefault();

  if (!capturedDescriptor) {
    statusDiv.textContent = 'Please capture your face first.';
    statusDiv.className = 'text-warning';
    return;
  }

  const empCode = document.getElementById('emp_code').value.trim();
  const name = document.getElementById('name').value.trim();
  const email = document.getElementById('email').value.trim();

  if (!empCode || !name) {
    statusDiv.textContent = 'Please fill in employee code and name.';
    statusDiv.className = 'text-warning';
    return;
  }

  try {
    // Get CSRF token from meta tag
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
    
    // Create a canvas to convert descriptor to image
    const canvasEl = document.createElement('canvas');
    const ctx = canvasEl.getContext('2d');
    canvasEl.width = video.videoWidth || 640;
    canvasEl.height = video.videoHeight || 480;
    
    // Draw the captured face from video
    ctx.drawImage(video, 0, 0, canvasEl.width, canvasEl.height);
    
    // Convert canvas to blob
    const blob = await new Promise((resolve, reject) => {
      canvasEl.toBlob(b => (b ? resolve(b) : reject(new Error('Failed to grab frame from webcam.'))), 'image/jpeg', 0.85);
    });
    
    // Create form data for Laravel API
    const formData = new FormData();
    formData.append('emp_code', empCode);
    formData.append('name', name);
    formData.append('email', email);
    formData.append('image', blob, 'face.jpg');
    formData.append('embedding', JSON.stringify(capturedDescriptor));
    
    const headers = { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' };
    if (csrfToken) {
      headers['X-CSRF-TOKEN'] = csrfToken;
    }

    statusDiv.textContent = 'Submitting face registration...';
    statusDiv.className = 'text-info';

    const res = await fetch(API_URL, {
      method: 'POST',
      headers: headers,
      body: formData
    });

    const text = await res.text();
    let data;
    try {
      data = text ? JSON.parse(text) : {};
    } catch (parseErr) {
      // Surface non-JSON responses (e.g., 500 HTML, 419, etc.)
      const snippet = text?.slice(0, 200) || '';
      throw new Error(`Non-JSON response (${res.status}). ${snippet}`);
    }

    if (!res.ok) {
      const backendMsg = data?.message || data?.error || data?.errors?.image?.[0] || data?.errors?.emp_code?.[0];
      throw new Error(backendMsg || `Request failed (status ${res.status})`);
    }

    statusDiv.innerHTML = `
      <div class="alert alert-success">
        <strong>Registration successful!</strong><br>
        Employee: ${data.employee_name} (${data.emp_code})<br>
        <a href="/employees" class="btn btn-sm btn-primary mt-2">Back to Employees</a>
        <button type="button" class="btn btn-sm btn-success mt-2 ms-2" onclick="location.reload()">Register Another Face</button>
      </div>
    `;
    
    // Log success details
    console.log('Registration successful:', data);
    
    // Clear form only if fields are not readonly (new employee registration)
    const empCodeField = document.getElementById('emp_code');
    const nameField = document.getElementById('name');
    const emailField = document.getElementById('email');
    
    if (!empCodeField.readOnly) {
      empCodeField.value = '';
      nameField.value = '';
      emailField.value = '';
    }
    capturedDescriptor = null;
    
  } catch (err) {
    console.error(err);
    statusDiv.textContent = err.message || String(err);
    statusDiv.className = 'text-danger';
  }
});
