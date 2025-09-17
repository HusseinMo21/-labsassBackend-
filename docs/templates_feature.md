# Report Templates Feature

## Overview

The Report Templates feature allows doctors and staff to create, manage, and use predefined report templates to streamline the report creation process. This feature significantly reduces repetitive typing and ensures consistency across similar reports.

## Features

### 1. Template Management
- **Create Templates**: Save current report fields as reusable templates
- **Choose Templates**: Select from existing templates to auto-fill report fields
- **Edit Templates**: Modify existing templates (only by creator or admin)
- **Delete Templates**: Remove templates (only by creator or admin)

### 2. Role-Based Access Control
- **Admin**: Can view, create, edit, and delete all templates
- **Doctor**: Can view public templates (created by admins/doctors) and their own templates
- **Staff**: Can view public templates and their own templates

### 3. Template Fields
Each template contains the following fields:
- **Name**: Descriptive name for the template (e.g., "Gastrointestinal – Colon Resection")
- **Clinical Data**: Patient's clinical information and history
- **Microscopic Description**: Detailed microscopic examination findings
- **Diagnosis**: Final diagnosis based on examination
- **Recommendations**: Treatment recommendations and follow-up instructions

## Database Schema

### Templates Table
```sql
CREATE TABLE templates (
    id BIGINT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    clinical_data TEXT NULL,
    microscopic TEXT NULL,
    diagnosis TEXT NULL,
    recommendations TEXT NULL,
    created_by BIGINT NULL,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
);
```

### Reports Table (Updated)
```sql
ALTER TABLE reports ADD COLUMN template_id BIGINT NULL;
ALTER TABLE reports ADD FOREIGN KEY (template_id) REFERENCES templates(id) ON DELETE SET NULL;
```

## API Endpoints

### Template Management
- `GET /api/templates` - List all available templates
- `POST /api/templates` - Create a new template
- `GET /api/templates/{id}` - Get specific template
- `PUT /api/templates/{id}` - Update template
- `DELETE /api/templates/{id}` - Delete template

### Special Endpoints
- `POST /api/templates/from-report` - Create template from current report data

## Usage Guide

### For Doctors and Staff

#### Using Templates
1. Navigate to **Reports & Analytics** → **Test Reports** tab
2. Click **"Add Test Report"** for any visit
3. In the **"Choose Template"** dropdown, select a template
4. The form fields will automatically populate with template data
5. Edit the fields as needed for the specific case
6. Save the report

#### Creating Templates
1. While creating or editing a report, fill in the desired fields
2. Click **"Save as Template"** button
3. Enter a descriptive name for the template
4. Click **"Save Template"**
5. The template will be available for future use

### For Administrators

#### Managing Templates
- Access all templates through the API endpoints
- Can edit or delete any template
- Can create system-wide templates for common procedures

## Sample Templates

The system comes with 5 pre-loaded sample templates:

1. **Gastrointestinal – Colon Resection**
   - For colorectal cancer cases
   - Includes standard pathology findings and recommendations

2. **Breast – Lumpectomy**
   - For breast cancer cases
   - Includes hormone receptor status and treatment recommendations

3. **Lung – Lobectomy**
   - For lung cancer cases
   - Includes staging information and adjuvant therapy recommendations

4. **Prostate – Radical Prostatectomy**
   - For prostate cancer cases
   - Includes Gleason score and follow-up recommendations

5. **Skin – Melanoma Excision**
   - For melanoma cases
   - Includes Breslow thickness and surveillance recommendations

## Technical Implementation

### Backend Components

#### Models
- **Template Model**: Handles template data and relationships
- **Report Model**: Updated to include template relationship

#### Controllers
- **TemplateController**: Manages CRUD operations for templates
- **API Routes**: RESTful endpoints for template management

#### Database Migrations
- `create_templates_table.php`: Creates the templates table
- `add_template_id_to_reports_table.php`: Adds template reference to reports

### Frontend Components

#### Reports Component Updates
- Added template selection dropdown
- Added "Save as Template" functionality
- Auto-fill logic when template is selected
- Template management modal

#### State Management
- Template list state
- Selected template state
- Template creation modal state

## Security Considerations

### Access Control
- Templates are filtered based on user role
- Users can only edit/delete their own templates (except admins)
- Public templates are created by admins and doctors

### Data Validation
- Template names are required and limited to 255 characters
- All template fields are optional but validated for proper format
- CSRF protection on all template operations

## Best Practices

### Template Naming
- Use descriptive names that clearly indicate the procedure type
- Include anatomical location and procedure type
- Examples: "Gastrointestinal – Colon Resection", "Breast – Lumpectomy"

### Template Content
- Keep clinical data concise but comprehensive
- Include standard microscopic findings for the procedure type
- Provide clear, actionable recommendations
- Use consistent terminology across similar templates

### Template Management
- Regularly review and update templates
- Remove outdated or unused templates
- Create templates for common procedures to improve efficiency

## Troubleshooting

### Common Issues

#### Templates Not Loading
- Check user permissions and role
- Verify API endpoint accessibility
- Check browser console for errors

#### Template Auto-fill Not Working
- Ensure template is properly selected
- Check that template data exists in database
- Verify frontend state management

#### Save as Template Failing
- Check CSRF token configuration
- Verify user has permission to create templates
- Ensure template name is provided

### Support
For technical issues or questions about the templates feature, contact the system administrator or refer to the API documentation.

## Future Enhancements

### Planned Features
- Template categories and tags
- Template versioning and history
- Bulk template import/export
- Template usage analytics
- Advanced template search and filtering

### Integration Opportunities
- Integration with Enhanced Reports system
- Template sharing between organizations
- AI-powered template suggestions
- Template validation and quality checks
