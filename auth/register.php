<?php
/**
 * JobNexus - Registration Page
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../classes/AuthController.php';

// Redirect if already logged in
if (isLoggedIn()) {
    redirect(BASE_URL);
}

$errors = [];
$formData = [
    'email' => '',
    'role' => $_GET['role'] ?? ROLE_SEEKER,
    'first_name' => '',
    'last_name' => '',
    'company_name' => '',
    'industry' => ''
];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid request. Please try again.';
    } else {
        $formData = [
            'email' => sanitize($_POST['email'] ?? ''),
            'password' => $_POST['password'] ?? '',
            'confirm_password' => $_POST['confirm_password'] ?? '',
            'role' => sanitize($_POST['role'] ?? ROLE_SEEKER),
            'first_name' => sanitize($_POST['first_name'] ?? ''),
            'last_name' => sanitize($_POST['last_name'] ?? ''),
            'company_name' => sanitize($_POST['company_name'] ?? ''),
            'industry' => sanitize($_POST['industry'] ?? ''),
            'website' => sanitize($_POST['website'] ?? ''),
            'agree_terms' => isset($_POST['agree_terms'])
        ];
        
        // Terms agreement check
        if (!$formData['agree_terms']) {
            $errors[] = 'You must agree to the Terms of Service';
        }
        
        if (empty($errors)) {
            $auth = new AuthController();
            $result = $auth->register($formData);
            
            if ($result['success']) {
                setFlash('success', 'Account created successfully! Welcome to JobNexus.');
                redirect($result['redirect']);
            } else {
                $errors = $result['errors'];
            }
        }
    }
}

// Industries list
$industries = [
    'Technology', 'Healthcare', 'Finance', 'Education', 'Manufacturing',
    'Retail', 'Hospitality', 'Construction', 'Transportation', 'Media',
    'Real Estate', 'Legal', 'Non-profit', 'Government', 'Other'
];

$pageTitle = 'Create Account';
include __DIR__ . '/../includes/header.php';
?>

<div class="auth-page">
    <div class="auth-container auth-register">
        <div class="auth-card">
            <div class="auth-header">
                <a href="<?php echo BASE_URL; ?>" class="logo">JobNexus</a>
                <h1>Create Account</h1>
                <p>Join thousands of professionals on JobNexus</p>
            </div>
            
            <?php if (!empty($errors)): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <div>
                        <?php foreach ($errors as $error): ?>
                            <p><?php echo sanitize($error); ?></p>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
            
            <!-- Role Selector -->
            <div class="role-selector">
                <label class="role-option <?php echo $formData['role'] === ROLE_SEEKER ? 'active' : ''; ?>">
                    <input type="radio" name="role" value="seeker" <?php echo $formData['role'] === ROLE_SEEKER ? 'checked' : ''; ?> onchange="toggleRole(this.value)">
                    <div class="role-content">
                        <i class="fas fa-user-tie"></i>
                        <span>Job Seeker</span>
                        <small>Find your dream job</small>
                    </div>
                </label>
                <label class="role-option <?php echo $formData['role'] === ROLE_HR ? 'active' : ''; ?>">
                    <input type="radio" name="role" value="hr" <?php echo $formData['role'] === ROLE_HR ? 'checked' : ''; ?> onchange="toggleRole(this.value)">
                    <div class="role-content">
                        <i class="fas fa-building"></i>
                        <span>Employer</span>
                        <small>Hire top talent</small>
                    </div>
                </label>
            </div>
            
            <form method="POST" action="" class="auth-form" data-validate enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                <input type="hidden" id="roleInput" name="role" value="<?php echo sanitize($formData['role']); ?>">
                
                <!-- Job Seeker Fields -->
                <div id="seekerFields" class="role-fields <?php echo $formData['role'] === ROLE_SEEKER ? 'active' : ''; ?>">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="first_name" class="form-label required">First Name</label>
                            <div class="input-group">
                                <i class="fas fa-user input-icon"></i>
                                <input 
                                    type="text" 
                                    id="first_name" 
                                    name="first_name" 
                                    class="form-control" 
                                    placeholder="John"
                                    value="<?php echo sanitize($formData['first_name']); ?>">
                                </div>
                        </div>
                        <div class="form-group">
                            <label for="last_name" class="form-label required">Last Name</label>
                            <div class="input-group">
                                <i class="fas fa-user input-icon"></i>
                                <input 
                                    type="text" 
                                    id="last_name" 
                                    name="last_name" 
                                    class="form-control" 
                                    placeholder="Doe"
                                    value="<?php echo sanitize($formData['last_name']); ?>">
                                </div>
                                </div>
                                </div>
                </div>
                
                <!-- Employer Fields -->
                <div id="hrFields" class="role-fields <?php echo $formData['role'] === ROLE_HR ? 'active' : ''; ?>">
                    <div class="form-group">
                        <label for="company_name" class="form-label required">Company Name</label>
                        <div class="input-group">
                            <i class="fas fa-building input-icon"></i>
                            <input 
                                type="text" 
                                id="company_name" 
                                name="company_name" 
                                class="form-control" 
                                placeholder="Your Company Inc."
                                value="<?php echo sanitize($formData['company_name']); ?>">
                            </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="industry" class="form-label">Industry</label>
                            <select id="industry" name="industry" class="form-control">
                                <option value="">Select Industry</option>
                                <?php foreach ($industries as $industry): ?>
                                    <option value="<?php echo $industry; ?>" <?php echo $formData['industry'] === $industry ? 'selected' : ''; ?>>
                                        <?php echo $industry; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="website" class="form-label">Website</label>
                            <div class="input-group">
                                <i class="fas fa-globe input-icon"></i>
                                <input 
                                    type="url" 
                                    id="website" 
                                    name="website" 
                                    class="form-control" 
                                    placeholder="https://example.com"
                                >
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="company_logo" class="form-label">Company Logo</label>
                        <div class="file-upload" id="logoUploadArea">
                            <input type="file" id="company_logo" name="company_logo" class="file-upload-input" accept="image/*">
                            <label for="company_logo" class="file-upload-label" id="logoUploadLabel">
                                <div class="upload-placeholder" id="logoPlaceholder">
                                    <i class="fas fa-cloud-upload-alt"></i>
                                    <span>Upload Logo</span>
                                    <small>PNG, JPG up to 2MB</small>
                                </div>
                                <div class="upload-preview" id="logoPreview" style="display: none;">
                                    <img id="logoPreviewImg" src="" alt="Logo Preview">
                                    <span class="preview-filename" id="logoFilename"></span>
                                    <button type="button" class="btn-remove-logo" id="removeLogo" onclick="removeLogo(event)">
                                        <i class="fas fa-times"></i> Remove
                                    </button>
                                </div>
                            </label>
                        </div>
                    </div>
                </div>
                
                <!-- Common Fields -->
                <div class="form-group">
                    <label for="email" class="form-label required">Email Address</label>
                    <div class="input-group">
                        <i class="fas fa-envelope input-icon"></i>
                        <input 
                            type="email" 
                            id="email" 
                            name="email" 
                            class="form-control" 
                            placeholder="you@example.com"
                            value="<?php echo sanitize($formData['email']); ?>"
                            required
                        >
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="password" class="form-label required">Password</label>
                        <div class="input-group">
                            <i class="fas fa-lock input-icon"></i>
                            <input 
                                type="password" 
                                id="password" 
                                name="password" 
                                class="form-control" 
                                placeholder="Min. 8 characters"
                                required
                                minlength="8"
                            >
                            <button type="button" class="btn-toggle-password" onclick="togglePassword('password')">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="confirm_password" class="form-label required">Confirm Password</label>
                        <div class="input-group">
                            <i class="fas fa-lock input-icon"></i>
                            <input 
                                type="password" 
                                id="confirm_password" 
                                name="confirm_password" 
                                class="form-control" 
                                placeholder="Confirm password"
                                required
                                data-match="#password"
                            >
                            <button type="button" class="btn-toggle-password" onclick="togglePassword('confirm_password')">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-check">
                        <input type="checkbox" name="agree_terms" class="form-check-input" required>
                        <span class="checkmark"></span>
                        <span>I agree to the <a href="#" target="_blank">Terms of Service</a> and <a href="#" target="_blank">Privacy Policy</a></span>
                    </label>
                </div>
                
                <button type="submit" class="btn btn-primary">
                    <span>Create Account</span>
                    <i class="fas fa-arrow-right"></i>
                </button>
            </form>
            
            <div class="auth-footer">
                <p>Already have an account? <a href="<?php echo BASE_URL; ?>/auth/login.php">Sign in</a></p>
            </div>
        </div>
        
        <div class="auth-visual">
            <div class="auth-visual-content">
                <h2>Start Your Journey</h2>
                <p id="visualText">Create your profile and get discovered by top employers worldwide.</p>
                
                <div class="auth-features">
                    <div class="auth-feature" id="feature1">
                        <i class="fas fa-check-circle"></i>
                        <span>Access to 50,000+ job listings</span>
                    </div>
                    <div class="auth-feature" id="feature2">
                        <i class="fas fa-check-circle"></i>
                        <span>Build your professional resume</span>
                    </div>
                    <div class="auth-feature" id="feature3">
                        <i class="fas fa-check-circle"></i>
                        <span>Get personalized job recommendations</span>
                    </div>
                </div>
                
                <div class="auth-visual-stats">
                    <div class="visual-stat">
                        <span class="number">50K+</span>
                        <span class="label">Active Jobs</span>
                    </div>
                    <div class="visual-stat">
                        <span class="number">10K+</span>
                        <span class="label">Companies</span>
                    </div>
                    <div class="visual-stat">
                        <span class="number">500K+</span>
                        <span class="label">Candidates</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
/* Auth Page Layout */
.auth-page {
    min-height: 100vh;
    display: flex;
    align-items: flex-start;
    justify-content: center;
    padding: 100px var(--spacing-md) var(--spacing-xl);
    background: var(--bg-primary);
}

.auth-container {
    display: grid;
    grid-template-columns: 1fr 1fr;
    max-width: 1100px;
    width: 100%;
    background: var(--bg-secondary);
    border-radius: var(--radius-xl);
    overflow: hidden;
    box-shadow: 0 25px 80px rgba(0, 0, 0, 0.5);
}

/* Auth Card (Form Side) */
.auth-card {
    padding: var(--spacing-2xl);
    display: flex;
    flex-direction: column;
    justify-content: flex-start;
    overflow-y: auto;
}

.auth-header {
    text-align: center;
    margin-bottom: var(--spacing-xl);
}

.auth-header .logo {
    font-family: var(--font-heading);
    font-size: 2rem;
    color: var(--accent-primary);
    margin-bottom: var(--spacing-md);
    display: block;
}

.auth-header h1 {
    font-size: 1.75rem;
    margin-bottom: var(--spacing-xs);
}

.auth-header p {
    color: var(--text-muted);
}

/* Role Selector */
.role-selector {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: var(--spacing-md);
    margin-bottom: var(--spacing-xl);
}

.role-option {
    cursor: pointer;
}

.role-option input {
    display: none;
}

.role-content {
    padding: var(--spacing-lg);
    background: var(--bg-tertiary);
    border: 2px solid var(--border-color);
    border-radius: var(--radius-lg);
    text-align: center;
    transition: all var(--transition-fast);
}

.role-option:hover .role-content {
    border-color: var(--border-light);
    transform: translateY(-2px);
}

.role-option.active .role-content,
.role-option input:checked + .role-content {
    border-color: var(--accent-primary);
    background: rgba(0, 230, 118, 0.08);
    box-shadow: 0 0 20px rgba(0, 230, 118, 0.15);
}

.role-content i {
    font-size: 2rem;
    color: var(--accent-primary);
    margin-bottom: var(--spacing-sm);
    display: block;
}

.role-content span {
    display: block;
    font-weight: 600;
    margin-bottom: 0.25rem;
    color: var(--text-primary);
}

.role-content small {
    color: var(--text-muted);
    font-size: 0.8rem;
}

/* Form Styles */
.auth-form .form-group {
    margin-bottom: var(--spacing-md);
}

.auth-form .form-label-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: var(--spacing-xs);
}

.auth-form label {
    font-weight: 500;
    color: var(--text-secondary);
    font-size: 0.9rem;
}

.auth-form .input-group {
    position: relative;
    display: flex;
    align-items: center;
}

.auth-form .input-icon {
    position: absolute;
    left: 1rem;
    top: 50%;
    transform: translateY(-50%);
    color: var(--text-muted);
    pointer-events: none;
    z-index: 1;
}

.auth-form .form-control {
    width: 100%;
    padding: 0.875rem 3rem 0.875rem 2.75rem;
    background: var(--bg-tertiary);
    border: 1px solid var(--border-color);
    border-radius: var(--radius-md);
    color: var(--text-primary);
    font-size: 0.95rem;
    transition: all var(--transition-fast);
}

.auth-form .form-control:focus {
    outline: none;
    border-color: var(--accent-primary);
    box-shadow: 0 0 0 3px rgba(0, 230, 118, 0.15);
}

.auth-form .form-control::placeholder {
    color: var(--text-muted);
}

.auth-form .form-control.no-icon {
    padding-left: 1rem;
}

.auth-form select.form-control {
    padding-left: 1rem;
    appearance: none;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' fill='%23888' viewBox='0 0 16 16'%3E%3Cpath d='M8 11L3 6h10l-5 5z'/%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: right 1rem center;
}

.auth-form textarea.form-control {
    padding-left: 1rem;
    min-height: 100px;
    resize: vertical;
}

/* Form Row */
.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: var(--spacing-md);
}

/* Role Fields Toggle */
.role-fields {
    display: none;
}

.role-fields.active {
    display: block;
    animation: fadeIn 0.3s ease;
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(-10px); }
    to { opacity: 1; transform: translateY(0); }
}

/* Password Toggle Button */
.btn-toggle-password {
    position: absolute;
    right: 0.75rem;
    top: 50%;
    transform: translateY(-50%);
    background: none;
    border: none;
    color: var(--text-muted);
    cursor: pointer;
    padding: 0.5rem;
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 1;
    transition: color var(--transition-fast);
}

.btn-toggle-password:hover {
    color: var(--accent-primary);
}

/* File Upload */
.file-upload {
    position: relative;
}

.file-upload-input {
    position: absolute;
    width: 0;
    height: 0;
    opacity: 0;
}

.file-upload-label {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: var(--spacing-lg);
    background: var(--bg-tertiary);
    border: 2px dashed var(--border-color);
    border-radius: var(--radius-md);
    cursor: pointer;
    transition: all var(--transition-fast);
}

.file-upload-label:hover {
    border-color: var(--accent-primary);
    background: rgba(0, 230, 118, 0.03);
}

.file-upload-label i {
    font-size: 1.5rem;
    color: var(--accent-primary);
    margin-bottom: var(--spacing-sm);
}

.file-upload-label span {
    color: var(--text-secondary);
    font-weight: 500;
}

.file-upload-label small {
    color: var(--text-muted);
    font-size: 0.8rem;
    margin-top: var(--spacing-xs);
}

/* Logo Preview Styles */
.upload-placeholder {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: var(--spacing-xs);
}

.upload-preview {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: var(--spacing-sm);
}

.upload-preview img {
    width: 80px;
    height: 80px;
    object-fit: contain;
    border-radius: var(--radius-sm);
    background: white;
    padding: 0.25rem;
}

.preview-filename {
    font-size: 0.85rem;
    color: var(--text-secondary);
    max-width: 200px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.btn-remove-logo {
    background: rgba(244, 67, 54, 0.1);
    border: 1px solid rgba(244, 67, 54, 0.3);
    color: #f44336;
    padding: 0.375rem 0.75rem;
    border-radius: var(--radius-sm);
    font-size: 0.8rem;
    cursor: pointer;
    transition: all var(--transition-fast);
    display: flex;
    align-items: center;
    gap: 0.25rem;
}

.btn-remove-logo:hover {
    background: rgba(244, 67, 54, 0.2);
}

.file-upload-label.has-preview {
    border-color: var(--accent-primary);
    background: rgba(0, 230, 118, 0.05);
}

/* Submit Button */
.auth-form .btn-primary {
    width: 100%;
    padding: 1rem;
    font-size: 1rem;
    font-weight: 600;
    margin-top: var(--spacing-md);
    background: linear-gradient(135deg, var(--accent-primary), var(--accent-secondary));
    border: none;
    border-radius: var(--radius-md);
    color: var(--bg-primary);
    cursor: pointer;
    transition: all var(--transition-fast);
    display: flex;
    align-items: center;
    justify-content: center;
    gap: var(--spacing-sm);
}

.auth-form .btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(0, 230, 118, 0.4);
}

/* Divider */
.auth-divider {
    display: flex;
    align-items: center;
    margin: var(--spacing-lg) 0;
    color: var(--text-muted);
    font-size: 0.85rem;
}

.auth-divider::before,
.auth-divider::after {
    content: '';
    flex: 1;
    height: 1px;
    background: var(--border-color);
}

.auth-divider span {
    padding: 0 var(--spacing-md);
}

/* Social Buttons */
.auth-social {
    display: flex;
    gap: var(--spacing-md);
}

.btn-social {
    flex: 1;
    padding: 0.875rem;
    background: var(--bg-tertiary);
    border: 1px solid var(--border-color);
    border-radius: var(--radius-md);
    color: var(--text-primary);
    font-size: 0.9rem;
    cursor: pointer;
    transition: all var(--transition-fast);
    display: flex;
    align-items: center;
    justify-content: center;
    gap: var(--spacing-sm);
}

.btn-social:hover {
    background: var(--bg-hover);
    border-color: var(--border-light);
    transform: translateY(-2px);
}

.btn-social.google:hover {
    border-color: #EA4335;
}

.btn-social.linkedin:hover {
    border-color: #0A66C2;
}

/* Auth Footer */
.auth-footer {
    text-align: center;
    margin-top: var(--spacing-lg);
    padding-top: var(--spacing-lg);
    border-top: 1px solid var(--border-color);
    color: var(--text-muted);
}

.auth-footer a {
    color: var(--accent-primary);
    font-weight: 500;
}

.auth-footer a:hover {
    text-decoration: underline;
}

/* Visual Side */
.auth-visual {
    background: linear-gradient(135deg, rgba(0, 230, 118, 0.15) 0%, rgba(0, 230, 118, 0.02) 100%);
    display: flex;
    flex-direction: column;
    justify-content: center;
    align-items: center;
    padding: var(--spacing-2xl);
    position: relative;
    overflow: hidden;
}

.auth-visual::before {
    content: '';
    position: absolute;
    top: -50%;
    left: -50%;
    width: 200%;
    height: 200%;
    background: radial-gradient(circle at center, rgba(0, 230, 118, 0.08) 0%, transparent 50%);
    animation: pulse 8s ease-in-out infinite;
}

@keyframes pulse {
    0%, 100% { transform: scale(1); opacity: 0.5; }
    50% { transform: scale(1.1); opacity: 0.8; }
}

.auth-visual-content {
    text-align: center;
    position: relative;
    z-index: 1;
    max-width: 400px;
}

.auth-visual-content h2 {
    font-size: 2rem;
    margin-bottom: var(--spacing-lg);
    color: var(--text-primary);
}

.auth-visual-content p {
    color: var(--text-secondary);
    font-size: 1.1rem;
    line-height: 1.7;
    margin-bottom: var(--spacing-xl);
}

/* Auth Features */
.auth-features {
    text-align: left;
    width: 100%;
}

.auth-feature {
    display: flex;
    align-items: center;
    gap: var(--spacing-md);
    padding: var(--spacing-sm) 0;
    color: var(--text-secondary);
    font-size: 0.95rem;
}

.auth-feature i {
    color: var(--accent-primary);
    width: 24px;
    text-align: center;
}

/* Visual Stats */
.auth-visual-stats {
    display: flex;
    gap: var(--spacing-xl);
    margin-top: var(--spacing-2xl);
    padding-top: var(--spacing-xl);
    border-top: 1px solid rgba(255, 255, 255, 0.1);
}

.visual-stat {
    text-align: center;
}

.visual-stat .number {
    display: block;
    font-size: 1.75rem;
    font-weight: 700;
    color: var(--accent-primary);
    font-family: var(--font-heading);
}

.visual-stat .label {
    font-size: 0.85rem;
    color: var(--text-muted);
}

/* Alert Styles */
.alert {
    padding: var(--spacing-md);
    border-radius: var(--radius-md);
    margin-bottom: var(--spacing-lg);
    display: flex;
    align-items: center;
    gap: var(--spacing-sm);
}

.alert-error {
    background: rgba(244, 67, 54, 0.1);
    border: 1px solid rgba(244, 67, 54, 0.3);
    color: #f44336;
}

.alert-success {
    background: rgba(0, 230, 118, 0.1);
    border: 1px solid rgba(0, 230, 118, 0.3);
    color: var(--accent-primary);
}

/* Custom Checkbox */
.form-check {
    display: inline-flex;
    flex-direction: row;
    align-items: center;
    gap: 0.75rem;
    cursor: pointer;
    font-size: 0.9rem;
    color: var(--text-secondary);
    margin: var(--spacing-md) 0;
    user-select: none;
}

.form-check input[type="checkbox"] {
    position: absolute;
    opacity: 0;
    cursor: pointer;
    height: 0;
    width: 0;
}

.form-check .checkmark {
    position: relative;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    height: 22px;
    width: 22px;
    min-width: 22px;
    min-height: 22px;
    flex-shrink: 0;
    background: var(--bg-tertiary);
    border: 2px solid var(--border-light);
    border-radius: var(--radius-sm);
    transition: all var(--transition-fast);
}

.form-check:hover .checkmark {
    border-color: var(--accent-primary);
    background: rgba(0, 230, 118, 0.1);
}

.form-check input:checked + .checkmark {
    background: var(--accent-primary);
    border-color: var(--accent-primary);
}

.form-check .checkmark:after {
    content: '';
    position: absolute;
    display: none;
    left: 6px;
    top: 2px;
    width: 5px;
    height: 10px;
    border: solid var(--bg-primary);
    border-width: 0 2px 2px 0;
    transform: rotate(45deg);
}

.form-check input:checked + .checkmark:after {
    display: block;
}

.form-check > span:last-child {
    display: inline;
}

.form-check a {
    color: var(--accent-primary);
    text-decoration: none;
    transition: opacity var(--transition-fast);
}

.form-check a:hover {
    opacity: 0.8;
    text-decoration: underline;
}

/* Responsive */
@media (max-width: 992px) {
    .auth-container {
        grid-template-columns: 1fr;
        max-width: 550px;
    }
    
    .auth-visual {
        display: none;
    }
}

@media (max-width: 768px) {
    .auth-page {
        padding: 80px var(--spacing-md) var(--spacing-xl);
    }
    
    .auth-card {
        padding: var(--spacing-lg);
    }
    
    .form-row {
        grid-template-columns: 1fr;
    }
    
    .role-selector {
        grid-template-columns: 1fr;
    }
    
    .auth-social {
        flex-direction: column;
    }
}
</style>

<script>
function togglePassword(inputId) {
    const input = document.getElementById(inputId);
    const button = input.parentElement.querySelector('.btn-toggle-password i');
    
    if (input.type === 'password') {
        input.type = 'text';
        button.classList.remove('fa-eye');
        button.classList.add('fa-eye-slash');
    } else {
        input.type = 'password';
        button.classList.remove('fa-eye-slash');
        button.classList.add('fa-eye');
    }
}

function toggleRole(role) {
    document.getElementById('roleInput').value = role;
    
    // Update role options
    document.querySelectorAll('.role-option').forEach(opt => {
        opt.classList.toggle('active', opt.querySelector('input').value === role);
    });
    
    // Toggle form fields
    document.getElementById('seekerFields').classList.toggle('active', role === 'seeker');
    document.getElementById('hrFields').classList.toggle('active', role === 'hr');
    
    // Update requirements
    document.getElementById('first_name').required = role === 'seeker';
    document.getElementById('last_name').required = role === 'seeker';
    document.getElementById('company_name').required = role === 'hr';
    
    // Update visual content
    const visualText = document.getElementById('visualText');
    const features = {
        seeker: [
            'Access to 50,000+ job listings',
            'Build your professional resume',
            'Get personalized job recommendations'
        ],
        hr: [
            'Post unlimited job listings',
            'Access our talent database',
            'Track and manage applications'
        ]
    };
    
    if (role === 'hr') {
        visualText.textContent = 'Find the perfect candidates for your team with our powerful recruitment tools.';
    } else {
        visualText.textContent = 'Create your profile and get discovered by top employers worldwide.';
    }
    
    const featuresList = features[role] || features.seeker;
    featuresList.forEach((text, index) => {
        document.getElementById('feature' + (index + 1)).querySelector('span').textContent = text;
    });
}

// Initialize on load
document.addEventListener('DOMContentLoaded', function() {
    const initialRole = document.getElementById('roleInput').value;
    toggleRole(initialRole);
    
    // Logo preview handling
    const logoInput = document.getElementById('company_logo');
    if (logoInput) {
        logoInput.addEventListener('change', handleLogoSelect);
    }
});

// Handle logo file selection
function handleLogoSelect(e) {
    const file = e.target.files[0];
    if (file) {
        // Validate file type
        if (!file.type.startsWith('image/')) {
            alert('Please select an image file.');
            e.target.value = '';
            return;
        }
        
        // Validate file size (2MB max)
        if (file.size > 2 * 1024 * 1024) {
            alert('File size must be less than 2MB.');
            e.target.value = '';
            return;
        }
        
        // Show preview
        const reader = new FileReader();
        reader.onload = function(event) {
            document.getElementById('logoPreviewImg').src = event.target.result;
            document.getElementById('logoFilename').textContent = file.name;
            document.getElementById('logoPlaceholder').style.display = 'none';
            document.getElementById('logoPreview').style.display = 'flex';
            document.getElementById('logoUploadLabel').classList.add('has-preview');
        };
        reader.readAsDataURL(file);
    }
}

// Remove logo
function removeLogo(e) {
    e.preventDefault();
    e.stopPropagation();
    
    const logoInput = document.getElementById('company_logo');
    logoInput.value = '';
    
    document.getElementById('logoPreviewImg').src = '';
    document.getElementById('logoFilename').textContent = '';
    document.getElementById('logoPlaceholder').style.display = 'flex';
    document.getElementById('logoPreview').style.display = 'none';
    document.getElementById('logoUploadLabel').classList.remove('has-preview');
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
