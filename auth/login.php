<?php
/**
 * JobNexus - Login Page
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../classes/AuthController.php';

// Redirect if already logged in
if (isLoggedIn()) {
  $role = getCurrentUserRole();
  switch ($role) {
    case ROLE_ADMIN:
      redirect(BASE_URL . '/admin/');
    case ROLE_HR:
      redirect(BASE_URL . '/hr/');
    default:
      redirect(BASE_URL . '/seeker/');
  }
}

$errors = [];
$email = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  // Verify CSRF token
  if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
    $errors[] = 'Invalid request. Please try again.';
  } else {
    $email = sanitize($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    $auth = new AuthController();
    $result = $auth->login($email, $password);

    if ($result['success']) {
      redirect($result['redirect']);
    } else {
      $errors = $result['errors'];
    }
  }
}

$pageTitle = 'Sign In';
include __DIR__ . '/../includes/header.php';
?>

<div class="auth-page">
  <div class="auth-container">
    <div class="auth-card">
      <div class="auth-header">
        <h1>Welcome Back</h1>
        <p>Sign in to continue to your account</p>
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

      <form method="POST" action="" class="auth-form" data-validate>
        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">

        <div class="form-group">
          <label for="email" class="form-label required">Email Address</label>
          <div class="input-icon">
            <i class="fas fa-envelope"></i>
            <input type="email" id="email" name="email" class="form-control" placeholder="you@example.com"
              value="<?php echo sanitize($email); ?>" required autofocus>
          </div>
        </div>

        <div class="form-group">
          <div class="form-label-row">
            <label for="password" class="form-label required">Password</label>
            <a href="<?php echo BASE_URL; ?>/auth/forgot-password.php" class="form-link">Forgot password?</a>
          </div>
          <div class="input-icon">
            <i class="fas fa-lock"></i>
            <input type="password" id="password" name="password" class="form-control" placeholder="Enter your password"
              required minlength="8">
            <button type="button" class="btn-toggle-password" onclick="togglePassword('password')">
              <i class="far fa-eye"></i>
            </button>
          </div>
        </div>

        <div class="form-group">
          <label class="form-check">
            <input type="checkbox" name="remember" class="form-check-input">
            <span class="checkmark"></span>
            <span>Remember me for 30 days</span>
          </label>
        </div>

        <button type="submit" class="btn btn-primary btn-block btn-lg">
          Sign In <i class="fas fa-arrow-right"></i>
        </button>
      </form>

      <p class="auth-footer">
        Don't have an account?
        <a href="<?php echo BASE_URL; ?>/auth/register.php">Create one</a>
      </p>
    </div>

    <div class="auth-visual">
      <div class="auth-visual-content">
        <div class="auth-visual-icon">
          <i class="fas fa-briefcase"></i>
        </div>
        <h2>Find Your Dream Job</h2>
        <p>Join thousands of professionals who have found their perfect career through JobNexus.</p>

        <div class="auth-stats">
          <div class="auth-stat">
            <span class="auth-stat-value">50K+</span>
            <span class="auth-stat-label">Active Jobs</span>
          </div>
          <div class="auth-stat">
            <span class="auth-stat-value">10K+</span>
            <span class="auth-stat-label">Companies</span>
          </div>
          <div class="auth-stat">
            <span class="auth-stat-value">1M+</span>
            <span class="auth-stat-label">Job Seekers</span>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<style>
  .auth-page {
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 100px 20px 40px;
    background: var(--bg-primary);
  }

  .auth-container {
    display: grid;
    grid-template-columns: 1fr 1fr;
    max-width: 1000px;
    width: 100%;
    background: var(--bg-card);
    border-radius: var(--radius-xl);
    overflow: hidden;
    box-shadow: var(--shadow-lg);
    border: 1px solid var(--border-color);
  }

  .auth-card {
    padding: var(--spacing-2xl);
  }

  .auth-header {
    text-align: center;
    margin-bottom: var(--spacing-xl);
  }

  .auth-header h1 {
    font-size: 2rem;
    margin-bottom: var(--spacing-sm);
  }

  .auth-header p {
    color: var(--text-secondary);
  }

  .auth-form {
    margin-bottom: var(--spacing-lg);
  }

  .form-label-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: var(--spacing-sm);
  }

  .form-link {
    font-size: 0.85rem;
    color: var(--accent-primary);
  }

  .input-icon {
    position: relative;
    display: flex;
    align-items: center;
  }

  .input-icon > i:first-child {
    position: absolute;
    left: 1rem;
    top: 50%;
    transform: translateY(-50%);
    color: var(--text-muted);
    pointer-events: none;
    z-index: 1;
  }

  .input-icon .form-control {
    padding-left: 2.75rem;
    padding-right: 3rem;
    width: 100%;
  }

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

  /* Custom Checkbox */
  .form-check {
    display: inline-flex;
    flex-direction: row;
    align-items: center;
    gap: 0.75rem;
    cursor: pointer;
    font-size: 0.9rem;
    color: var(--text-secondary);
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

  .auth-divider {
    display: flex;
    align-items: center;
    gap: var(--spacing-md);
    margin: var(--spacing-lg) 0;
    color: var(--text-muted);
    font-size: 0.9rem;
  }

  .auth-divider::before,
  .auth-divider::after {
    content: '';
    flex: 1;
    height: 1px;
    background: var(--border-color);
  }

  .auth-social {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: var(--spacing-md);
    margin-bottom: var(--spacing-lg);
  }

  .btn-social {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: var(--spacing-sm);
    padding: 0.875rem;
    background: var(--bg-tertiary);
    border: 1px solid var(--border-color);
    border-radius: var(--radius-md);
    color: var(--text-primary);
    font-weight: 500;
    transition: all var(--transition-fast);
  }

  .btn-social:hover {
    background: var(--bg-hover);
    border-color: var(--border-light);
  }

  .btn-google i {
    color: #ea4335;
  }

  .btn-linkedin i {
    color: #0077b5;
  }

  .auth-footer {
    text-align: center;
    color: var(--text-secondary);
  }

  .auth-footer a {
    color: var(--accent-primary);
    font-weight: 500;
  }

  .auth-visual {
    background: linear-gradient(135deg, rgba(0, 230, 118, 0.1) 0%, var(--bg-secondary) 100%);
    display: flex;
    align-items: center;
    justify-content: center;
    padding: var(--spacing-2xl);
    position: relative;
    overflow: hidden;
  }

  .auth-visual::before {
    content: '';
    position: absolute;
    top: -50%;
    right: -50%;
    width: 100%;
    height: 100%;
    background: radial-gradient(circle, rgba(0, 230, 118, 0.15) 0%, transparent 60%);
  }

  .auth-visual-content {
    text-align: center;
    position: relative;
    z-index: 1;
  }

  .auth-visual-icon {
    width: 100px;
    height: 100px;
    border-radius: var(--radius-xl);
    background: rgba(0, 230, 118, 0.15);
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto var(--spacing-xl);
    font-size: 2.5rem;
    color: var(--accent-primary);
  }

  .auth-visual h2 {
    font-size: 1.75rem;
    margin-bottom: var(--spacing-md);
  }

  .auth-visual p {
    color: var(--text-secondary);
    max-width: 300px;
    margin: 0 auto var(--spacing-xl);
  }

  .auth-stats {
    display: flex;
    justify-content: center;
    gap: var(--spacing-xl);
  }

  .auth-stat {
    text-align: center;
  }

  .auth-stat-value {
    display: block;
    font-family: var(--font-heading);
    font-size: 1.75rem;
    color: var(--accent-primary);
  }

  .auth-stat-label {
    font-size: 0.85rem;
    color: var(--text-muted);
  }

  @media (max-width: 768px) {
    .auth-container {
      grid-template-columns: 1fr;
    }

    .auth-visual {
      display: none;
    }

    .auth-card {
      padding: var(--spacing-xl);
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
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>