# System Diagrams: Web & Mobile Face Attendance Platform

This document captures the high-level views the user requested: context, data flow, use cases, entity relationships, and the operational flow spanning the Laravel backend, FastAPI recognition service, and Flutter mobile client.

---

## 1. Context Diagram

```mermaid
flowchart LR
    subgraph Clients
        A[Admin Web Browser]
        B[Face Capture Device<br/> (webcam or kiosk)]
        C[Flutter Mobile App]
    end

    subgraph Backend
        D[Laravel API<br/> routes/web.php & routes/api.php]
        E[(Database<br/> employees/face_templates/attendance_logs/settings)]
        F[Storage (images/embeddings)]
    end

    G[FastAPI Recognition Service]

    A <--> D
    B --> D
    C <--> D
    D <--> E
    D --> F
    D <--> G
```

**Key points**
- Every client (web admin, face device, Flutter app) communicates with Laravel; only Laravel talks to the database.
- FastAPI is invoked only by Laravel when embeddings need to be generated or matched.

---

## 2. Data Flow Diagram (Level 1)

```mermaid
flowchart TB
    subgraph Processes
        P1[1. Face Registration<br/> FaceController]
        P2[2. Recognition & Logging<br/> RecognitionController]
        P3[3. Mobile Login<br/> MobileAuthController]
        P4[4. Attendance Reporting<br/> AttendanceController]
    end

    DS1[(Employees)]
    DS2[(Face Templates)]
    DS3[(Attendance Logs)]
    DS4[(Settings)]

    extAdmin[Admin/Webcam]
    extDevice[Recognition Device]
    extMobile[Flutter Employee]
    extFastAPI[FastAPI Service]

    extAdmin -->|Form + Image| P1
    P1 -->|Employee profile| DS1
    P1 -->|Embeddings| DS2
    P1 --> extFastAPI
    extFastAPI --> P1

    extDevice -->|Image/Embedding| P2
    P2 --> extFastAPI
    extFastAPI --> P2
    P2 -->|Check-in/out| DS3
    P2 -->|Schedule lookup| DS4
    P2 -->|Result JSON| extDevice

    extMobile -->|Credentials| P3
    P3 -->|Token & profile| extMobile
    P3 -->|Password check| DS1

    extMobile -->|Bearer token + query| P4
    P4 -->|Logs read| DS3
    P4 -->|Schedule read| DS4
    P4 -->|History JSON| extMobile
```

---

## 3. Use Case Diagram

```mermaid
flowchart LR
    actorAdmin((Admin))
    actorDevice((Attendance Device))
    actorEmployee((Employee))

    useManageEmployees([Manage Employees])
    useRegisterFace([Register Face])
    useConfigureSchedule([Configure Attendance Schedule])
    useCaptureAttendance([Capture Check-in/out])
    useMobileLogin([Mobile Login])
    useViewHistory([View Attendance History])

    actorAdmin --> useManageEmployees
    actorAdmin --> useRegisterFace
    actorAdmin --> useConfigureSchedule
    actorDevice --> useCaptureAttendance
    actorEmployee --> useMobileLogin
    actorEmployee --> useViewHistory

    useManageEmployees -->|Includes| useRegisterFace
    useCaptureAttendance -->|Uses schedule| useConfigureSchedule
    useViewHistory -->|Requires token| useMobileLogin
```

---

## 4. Entity Relationship Diagram

```mermaid
erDiagram
    EMPLOYEES ||--o{ FACE_TEMPLATES : "has many"
    EMPLOYEES ||--o{ ATTENDANCE_LOGS : "has many"
    SETTINGS ||..|| SETTINGS : "key/value store"

    EMPLOYEES {
        bigint id PK
        string emp_code
        string first_name
        string last_name
        string email
        string department
        string position
        string password (hashed)
        boolean active
    }

    FACE_TEMPLATES {
        bigint id PK
        bigint employee_id FK
        string image_path
        json embedding
        string model
        float score
        string source
    }

    ATTENDANCE_LOGS {
        bigint id PK
        bigint employee_id FK
        string emp_code
        enum action (time_in/time_out)
        timestamp logged_at
        boolean is_late
        float confidence
        boolean liveness_pass
        bigint device_id
        json meta
    }

    SETTINGS {
        bigint id PK
        string key UNIQUE
        json value
    }
```

Notes:
- `settings` acts as a simple key-value store; the `attendance.schedule` key drives both recognition gating and attendance reporting.
- `attendance_logs.emp_code` duplicates the employee code for resilience when the foreign key is null (e.g., failed recognition).

---

## 5. Operational Flowchart

```mermaid
flowchart TD
    A[Start] --> B[Admin logs into Laravel dashboard]
    B --> C{Employee exists?}
    C -->|No| D[Create employee profile]
    C -->|Yes| E[Open face registration page]
    D --> E
    E --> F[Capture face + submit to /api/register-face]
    F --> G[FastAPI generates embedding]
    G --> H[Store employee/template records]
    H --> I[Recognition devices active]

    I --> J[Device sends image to /api/recognize-proxy]
    J --> K[FastAPI match + cosine scoring]
    K --> L{Score >= threshold<br/> and schedule open?}
    L -->|No| M[Return rejection + reason]
    L -->|Yes| N[Write attendance_log entry]
    N --> O[Send success response to device]

    O --> P[Employee opens Flutter app]
    P --> Q{Cached token valid?}
    Q -->|No| R[Call /api/mobile/login<br/> with email/emp_code + password]
    Q -->|Yes| S[Skip login]
    R --> S
    S --> T[App requests /api/attendance with token]
    T --> U[Laravel aggregates logs + schedule info]
    U --> V[Display history & today's status]
    V --> W[End]
```

---

Refer to this document alongside `docs/WEB_MOBILE_DB_FLOW.md` for textual explanations of each component.