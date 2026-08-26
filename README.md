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

After the first startup, sign in to the app with the seeded development account:

- Email: `test@example.com`
- Password: `password`

To seed the account manually after the stack is already running:

```bash
docker compose --env-file app/.env exec backend php artisan db:seed --force
```

Nginx serves the CRM at `/`, forwards `/api/*` and `/up` to Laravel, and keeps
PHP-FPM and MySQL on the private Compose network. TLS should be terminated by
an external load balancer or added to Nginx before exposing the deployment to
the public internet.