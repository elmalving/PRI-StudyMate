# StudyMate

### Chat-bot for KI/PRI course

* Fullstack (Front-End & Back-End): Mykyta Hromov

---

## Prerequisites

- [Docker Desktop](https://www.docker.com/products/docker-desktop/) (includes `docker` and `docker compose`)

---

## Running the Project

**Start all services with Docker:**
```bash
docker compose up --build
```

This will start:
- **Frontend** — React app at `http://localhost:4173`
- **Backend** — PHP API at `http://localhost:5000`
- **MongoDB** — Database at `localhost:27017`

---

## Stopping the Project

**Stop the services:**
```bash
docker compose down
```

---

## API Endpoints

| Method | Route       | Description                |
|--------|-------------|----------------------------|
| GET    | `/@me`      | Get current logged-in user |
| POST   | `/register` | Register a new user        |
| POST   | `/login`    | Login                      |
| POST   | `/logout`   | Logout                     |

### Register body:
```json
{
  "email": "user@example.com",
  "password": "password123",
  "firstName": "John",
  "lastName": "Doe",
  "degree": "Computer Science"
}
```

### Login body:
```json
{
  "email": "user@example.com",
  "password": "password123"
}
```
