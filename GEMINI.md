# Gemini Project Guidelines: Symfony CRON Job Manager

This file provides project-specific guidelines for the Gemini AI assistant.

## Project Overview

- **Purpose:** This is a Symfony application designed to manage and execute various CRON jobs.
- **Core Technologies:**
    - Symfony Framework
    - Doctrine ORM for database interactions.
    - Symfony Console for command-line tasks.

## Development Workflow

### Creating New CRON Jobs

All CRON jobs are implemented as Symfony Console Commands. To create a new job:

1.  Use the Symfony MakerBundle to generate a new command class:
    ```bash
    php bin/console make:command YourNewCommandNameCommand
    ```
2.  The new command will be created in the `src/Command/` directory.

### Business Logic

- **Services:** For any complex business logic (e.g., interacting with Google Drive APIs, performing database backups), create a dedicated service class inside the `src/Service/` directory.
- **Dependency Injection:** Inject these services into your command classes via the constructor. This keeps the commands clean and focused on handling input/output, while the services handle the core logic.

### Database

- **ORM:** Use Doctrine for all database operations.
- Don't use Entities and EntityManager for this project

## Coding Style & Conventions

- **Style:** Adhere to the official [Symfony Coding Standards](https://symfony.com/doc/current/contributing/code/standards.html).
- **Naming:**
    - Commands: Suffix with `Command` (e.g., `BackupDatabaseCommand`).
    - Services: Name them based on their responsibility (e.g., `GoogleDriveUploader`, `DatabaseBackupManager`).
- **Configuration:** All environment-specific configuration, especially secrets and database URLs, should be managed in `.env.local` or other environment-specific `.env` files. Do not commit these files to version control.
