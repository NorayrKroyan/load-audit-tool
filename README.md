# Load Audit Tool Bundle

This bundle gives you:

- `frontend/` — complete Vue + Vite app source
- `backend-overlay/` — Laravel backend custom files to be copied into a fresh Laravel app
- `setup.ps1` — PowerShell script that builds the exact structure:
  - `backend/`
  - `frontend/`

## One-command setup

From the extracted folder, run:

```powershell
powershell -ExecutionPolicy Bypass -File .\setup.ps1
```

The script will:

1. Create a fresh Laravel backend in `backend`
2. Run `php artisan install:api`
3. Copy the custom backend files from `backend-overlay`
4. Ask for DB settings and write them into `backend/.env`
5. Generate the app key
6. Run migrations
7. Run the `load-audit:backfill-entered-at` command
8. Install frontend npm packages

## Run after setup

Backend:
```powershell
cd .\backend
php artisan serve
```

Frontend:
```powershell
cd .\frontend
npm run dev
```

## Final structure after setup

```text
load-audit-tool
├── backend
├── frontend
├── backend-overlay
├── setup.ps1
└── README.md
```

If you want, you can delete `backend-overlay` after setup because it is only the template source.
