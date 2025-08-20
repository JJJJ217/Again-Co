# Again-Co
This is a UTS group project for Advanced Software Development

## Project Overview
Again-Co is a cloud-native application developed as part of the Advanced Software Development course at UTS. The project demonstrates modern software development practices using Azure cloud services, agile methodologies, and continuous integration/continuous deployment (CI/CD) pipelines.

## Azure Features

### Cloud Services Integration
- **Azure App Service**: Web application hosting and deployment platform
- **Azure DevOps**: Project management, source control, and CI/CD pipelines
- **Azure SQL Database**: Scalable cloud database solution
- **Azure Key Vault**: Secure secrets and configuration management
- **Azure Monitor**: Application performance monitoring and logging
- **Azure Storage**: Blob storage for static assets and file management

### DevOps Integration
- **Azure Pipelines**: Automated build, test, and deployment workflows
- **Azure Repos**: Git-based source control with branch policies
- **Azure Boards**: Agile project management with work items and sprints
- **Azure Artifacts**: Package management and dependency storage

## User Stories

### Core Functionality
1. **As a user**, I want to register for an account so that I can access personalized features
   - Acceptance Criteria: User can create account with email and password
   - Acceptance Criteria: User receives email confirmation
   - Acceptance Criteria: User can log in with created credentials

2. **As a user**, I want to securely log into the application so that my data remains protected
   - Acceptance Criteria: User authentication is handled securely
   - Acceptance Criteria: Session management prevents unauthorized access
   - Acceptance Criteria: Password requirements enforce security standards

3. **As a user**, I want the application to be available 24/7 so that I can access it anytime
   - Acceptance Criteria: Application hosted on reliable Azure infrastructure
   - Acceptance Criteria: Minimal downtime during deployments
   - Acceptance Criteria: Performance monitoring ensures responsiveness

### Administrative Features
4. **As an administrator**, I want to monitor application performance so that I can ensure optimal user experience
   - Acceptance Criteria: Real-time monitoring dashboard available
   - Acceptance Criteria: Automated alerts for performance issues
   - Acceptance Criteria: Detailed logging for troubleshooting

5. **As a developer**, I want automated deployment pipelines so that I can deliver features quickly and safely
   - Acceptance Criteria: Code changes trigger automated builds
   - Acceptance Criteria: Automated testing prevents broken deployments
   - Acceptance Criteria: Zero-downtime deployment to production

## Development Setup

### Prerequisites
- Azure subscription with appropriate permissions
- Azure DevOps organization access
- Git for source control

### Local Development
1. Clone the repository
2. Configure Azure CLI with project credentials
3. Set up local development environment
4. Connect to Azure services for testing

### Deployment
The project uses Azure Pipelines for automated deployment. See `azure-pipelines.yml` for configuration details.

## Contributing
This project follows agile development practices with sprint-based delivery and continuous integration.
