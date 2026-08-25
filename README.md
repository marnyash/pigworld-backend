# pigworld-backend

## Deployment

Copy `backend/.env.docker.example` to `backend/app/.env` and set a real
`APP_KEY`, database passwords, and `CORS_ALLOWED_ORIGINS`. From the workspace
root, start the stack with:

```bash
cd backend
docker compose --env-file app/.env up --build -d
docker compose --env-file app/.env ps
```

Nginx serves the CRM at `/`, forwards `/api/*` and `/up` to Laravel, and keeps
PHP-FPM and MySQL on the private Compose network. TLS should be terminated by
an external load balancer or added to Nginx before exposing the deployment to
the public internet.