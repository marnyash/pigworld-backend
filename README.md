# pigworld-backend

## Deployment

Copy `backend/.env.docker.example` to `backend/app/.env` and set a real
`APP_KEY`, database passwords, `APP_URL`, and `CORS_ALLOWED_ORIGINS`. Never use
the example passwords in a deployed environment. From the workspace root,
start the stack with:

```bash
cd backend
docker compose --env-file app/.env up --build -d
docker compose --env-file app/.env ps
```

For development and staging only, sign in to the app with the seeded tester
account:

- Email: `test@example.com`
- Password: `password`

The tester account is not created in production. The Compose startup command
also skips the database seeder when `APP_ENV=production`; production data must
be created through an explicitly reviewed migration or administrative process.

To seed the account manually after the stack is already running:

```bash
docker compose --env-file app/.env exec backend php artisan db:seed --force
```

Nginx serves the CRM at `/`, forwards `/api/*` and `/up` to Laravel, and keeps
PHP-FPM and MySQL on the private Compose network. TLS must be terminated by an
external load balancer or added to Nginx before exposing the deployment to the
public internet. Set `CORS_ALLOWED_ORIGINS` to exact trusted origins; do not
use a wildcard for the browser CRM.

## Security coverage

- Logout revokes the user's active refresh tokens as well as the access token.
- Authenticated subscription-plan mutations require a farm owner or CRM
	`admin`/`finance` role at both the request and controller layers.
- The CRM currently stores bearer tokens in browser `localStorage`. A future
	hardening pass should migrate browser authentication to secure, HttpOnly
	cookies or use a backend-for-frontend session.
- Production deployment still requires HTTPS enforcement, secret management,
	and a managed durable database/storage service.