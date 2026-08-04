# Project: Student Web Hosting Platform

## Product Vision

Build a self-service web hosting platform for beginners, students, and developers who need simple hosting for small websites and testing projects.

The service allows users to:
- Register an account
- Choose a hosting plan
- Upload website files as ZIP
- Deploy automatically
- Receive a public URL
- Manage their website from a simple dashboard

The target user does not understand Git, servers, Docker, or DevOps.

The experience should feel like simplified cPanel:
- Upload files
- Click deploy
- Website goes live

Do not require:
- GitHub
- GitLab
- CLI commands
- Server knowledge


# Business Model

The platform is a paid monthly service.

Example plans:

Free Trial:
- 1 website
- Limited storage
- Limited bandwidth

Student:
- 1 website
- More storage
- Database access

Developer:
- Multiple websites
- More storage
- More bandwidth

The system should be designed to support subscription billing later.


# Target Infrastructure

Initial deployment:

- Single VPS
- Oracle Cloud ARM64 Free Tier
- Docker based isolation

Future possibility:
- Multiple VPS nodes

Do not design for Kubernetes or complex cloud infrastructure.


# Technology Stack

Backend:
- Laravel 12
- PHP 8.3
- FilamentPHP
- MariaDB/MySQL

Infrastructure:
- Docker
- Traefik reverse proxy
- Cloudflare DNS
- Let's Encrypt SSL

Storage:
- Local VPS storage initially
- Object storage later if needed


# Core Architecture

The Laravel application is the control panel.

It manages:

- Users
- Plans
- Projects
- Uploads
- Deployments
- Usage tracking


Each customer website runs inside its own Docker container.

Example:

Customer A:
customer-a.example.com
        |
        v
Docker container A


Customer B:
customer-b.example.com
        |
        v
Docker container B


The customer should never directly manage Docker.


# Supported Website Types

Initial supported runtimes:

## Static Website
- HTML
- CSS
- JavaScript

## PHP Website
- PHP 8.3
- Nginx
- PHP-FPM


Future:
- Laravel applications
- Node.js applications


# User Experience

User journey:

1. Register account

2. Select plan

3. Create website project

4. Upload ZIP file

5. System validates upload

6. System deploys website

7. User receives:

https://username.example.com


Dashboard shows:

- Website status
- URL
- Storage usage
- Bandwidth usage
- Deployment history
- Restart button
- Redeploy button


# Resource Management

Resources are shared between users.

Do not reserve CPU/RAM per user.

Enforce quotas:

Storage:
- Maximum storage per plan

Bandwidth:
- Monthly bandwidth limit

Database:
- Database size limit

Upload:
- Maximum ZIP size
- Maximum extracted size
- Maximum file count


Example:

Student Plan:
- 2GB storage
- 20GB bandwidth/month
- 200MB database
- 1 website


# Security Requirements

Always consider:

File uploads:
- Prevent ZIP path traversal
- Validate file types
- Limit extracted size
- Limit file count

Containers:
- No privileged containers
- No host filesystem access
- Isolated project directories

Users must never affect other users.


# Admin Features

Admin dashboard:

Manage:
- Users
- Plans
- Projects
- Deployments
- Resource usage

Admin actions:
- Suspend website
- Restart container
- Delete project
- View logs


# Development Principles

Important:

Keep it simple.

Do not introduce:
- Kubernetes
- Microservices
- Over-engineered architecture

Prefer:
- Laravel services
- Simple database design
- Clear separation of responsibilities


# Development Phases

## Phase 1: Foundation

Build:
- Laravel setup
- Authentication
- Filament admin
- User dashboard
- Project CRUD
- Hosting plans structure

No Docker deployment yet.


## Phase 2: File Management

Build:
- ZIP upload
- File validation
- Safe extraction
- Storage quota checking
- Project file management


## Phase 3: Deployment Engine

Build:
- Docker container creation
- Container lifecycle management
- Nginx setup
- Traefik routing
- Subdomain deployment


## Phase 4: Database Hosting

Build:
- MySQL database provisioning
- Database credentials management
- Database quota tracking


## Phase 5: Billing

Build:
- Subscription plans
- Payment integration
- Account limits enforcement


## Phase 6: Monitoring

Build:
- Usage tracking
- Resource monitoring
- Logs
- Admin alerts


# Coding Workflow

When implementing a phase:

1. Read this document first.
2. Only implement the requested phase.
3. Do not skip ahead.
4. Explain important decisions briefly.
5. Keep code production quality.
6. Show changed files.
7. Include migration/model/service/controller changes where relevant.
8. Add tests for important functionality.