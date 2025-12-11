/**
 * JobNexus - Main JavaScript
 * Premium Job Portal Application
 */

// =====================================================
// DOM Ready
// =====================================================
document.addEventListener("DOMContentLoaded", function () {
  initNavbar();
  initMobileMenu();
  initModals();
  initToasts();
  initFormValidation();
  initDropdowns();
  initSearchFilters();
  initJobCards();
  initTabs();
});

// =====================================================
// Navbar Scroll Effect
// =====================================================
function initNavbar() {
  const navbar = document.querySelector(".navbar");
  if (!navbar) return;

  let lastScroll = 0;

  window.addEventListener("scroll", () => {
    const currentScroll = window.pageYOffset;

    if (currentScroll > 50) {
      navbar.classList.add("scrolled");
    } else {
      navbar.classList.remove("scrolled");
    }

    lastScroll = currentScroll;
  });
}

// =====================================================
// Mobile Menu Toggle
// =====================================================
function initMobileMenu() {
  const toggle = document.querySelector(".mobile-toggle");
  const sidebar = document.querySelector(".sidebar");
  const overlay = document.querySelector(".sidebar-overlay");

  if (toggle && sidebar) {
    toggle.addEventListener("click", () => {
      sidebar.classList.toggle("active");
      document.body.classList.toggle("sidebar-open");
    });

    if (overlay) {
      overlay.addEventListener("click", () => {
        sidebar.classList.remove("active");
        document.body.classList.remove("sidebar-open");
      });
    }
  }
}

// =====================================================
// Modals
// =====================================================
function initModals() {
  // Open modal triggers
  document.querySelectorAll("[data-modal-open]").forEach((trigger) => {
    trigger.addEventListener("click", (e) => {
      e.preventDefault();
      const modalId = trigger.dataset.modalOpen;
      openModal(modalId);
    });
  });

  // Close modal triggers
  document.querySelectorAll("[data-modal-close]").forEach((trigger) => {
    trigger.addEventListener("click", () => {
      closeAllModals();
    });
  });

  // Close on overlay click
  document.querySelectorAll(".modal-overlay").forEach((overlay) => {
    overlay.addEventListener("click", (e) => {
      if (e.target === overlay) {
        closeAllModals();
      }
    });
  });

  // Close on Escape key
  document.addEventListener("keydown", (e) => {
    if (e.key === "Escape") {
      closeAllModals();
    }
  });
}

function openModal(modalId) {
  const modal = document.getElementById(modalId);
  if (modal) {
    modal.classList.add("active");
    document.body.style.overflow = "hidden";
  }
}

function closeModal(modalId) {
  const modal = document.getElementById(modalId);
  if (modal) {
    modal.classList.remove("active");
    document.body.style.overflow = "";
  }
}

function closeAllModals() {
  document.querySelectorAll(".modal-overlay.active").forEach((modal) => {
    modal.classList.remove("active");
  });
  document.body.style.overflow = "";
}

// =====================================================
// Toast Notifications
// =====================================================
function initToasts() {
  // Auto-hide existing toasts
  document.querySelectorAll(".toast.show").forEach((toast) => {
    setTimeout(() => {
      toast.classList.remove("show");
      setTimeout(() => toast.remove(), 300);
    }, 5000);
  });
}

function showToast(message, type = "info") {
  const container =
    document.querySelector(".toast-container") || createToastContainer();

  const toast = document.createElement("div");
  toast.className = `toast toast-${type}`;
  toast.innerHTML = `
        <i class="fas fa-${getToastIcon(type)}"></i>
        <span>${message}</span>
        <button class="toast-close" onclick="this.parentElement.remove()">
            <i class="fas fa-times"></i>
        </button>
    `;

  container.appendChild(toast);

  // Trigger animation
  setTimeout(() => toast.classList.add("show"), 10);

  // Auto remove
  setTimeout(() => {
    toast.classList.remove("show");
    setTimeout(() => toast.remove(), 300);
  }, 5000);
}

function createToastContainer() {
  const container = document.createElement("div");
  container.className = "toast-container";
  document.body.appendChild(container);
  return container;
}

function getToastIcon(type) {
  const icons = {
    success: "check-circle",
    error: "exclamation-circle",
    warning: "exclamation-triangle",
    info: "info-circle",
  };
  return icons[type] || "info-circle";
}

// =====================================================
// Form Validation
// =====================================================
function initFormValidation() {
  document.querySelectorAll("form[data-validate]").forEach((form) => {
    form.addEventListener("submit", function (e) {
      if (!validateForm(this)) {
        e.preventDefault();
      }
    });

    // Real-time validation
    form.querySelectorAll("input, textarea, select").forEach((field) => {
      field.addEventListener("blur", () => validateField(field));
      field.addEventListener("input", () => {
        if (field.classList.contains("is-invalid")) {
          validateField(field);
        }
      });
    });
  });
}

function validateForm(form) {
  let isValid = true;

  form.querySelectorAll("[required]").forEach((field) => {
    if (!validateField(field)) {
      isValid = false;
    }
  });

  return isValid;
}

function validateField(field) {
  const value = field.value.trim();
  let isValid = true;
  let errorMessage = "";

  // Required check
  if (field.hasAttribute("required") && !value) {
    isValid = false;
    errorMessage = "This field is required";
  }

  // Email validation
  if (isValid && field.type === "email" && value) {
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    if (!emailRegex.test(value)) {
      isValid = false;
      errorMessage = "Please enter a valid email address";
    }
  }

  // Min length
  if (isValid && field.minLength > 0 && value.length < field.minLength) {
    isValid = false;
    errorMessage = `Minimum ${field.minLength} characters required`;
  }

  // Password confirmation
  if (isValid && field.dataset.match) {
    const matchField = document.querySelector(field.dataset.match);
    if (matchField && value !== matchField.value) {
      isValid = false;
      errorMessage = "Passwords do not match";
    }
  }

  // HTML5 Pattern Validation
  if (isValid && field.pattern && value) {
    const regex = new RegExp('^' + field.pattern + '$');
    if (!regex.test(value)) {
      isValid = false;
      errorMessage = field.title || "Invalid format";
    }
  }

  // Number Validation
  if (isValid && field.type === 'number' && value) {
    // Check min
    if (field.min && parseFloat(value) < parseFloat(field.min)) {
        isValid = false;
        errorMessage = `Value must be at least ${field.min}`;
    }
    // Check max
    if (isValid && field.max && parseFloat(value) > parseFloat(field.max)) {
        isValid = false;
        errorMessage = `Value must be at most ${field.max}`;
    }
    // Check valid number
    if (isValid && isNaN(parseFloat(value))) {
        isValid = false;
        errorMessage = "Please enter a valid number";
    }
  }

  // Update UI
  updateFieldValidation(field, isValid, errorMessage);

  return isValid;
}

function updateFieldValidation(field, isValid, errorMessage) {
  const wrapper = field.closest(".form-group");
  if (!wrapper) return;

  let errorEl = wrapper.querySelector(".form-error");

  if (isValid) {
    field.classList.remove("is-invalid");
    field.classList.add("is-valid");
    if (errorEl) errorEl.remove();
  } else {
    field.classList.remove("is-valid");
    field.classList.add("is-invalid");

    if (!errorEl) {
      errorEl = document.createElement("div");
      errorEl.className = "form-error";
      wrapper.appendChild(errorEl);
    }
    errorEl.textContent = errorMessage;
  }
}

// =====================================================
// Dropdowns
// =====================================================
function initDropdowns() {
  document.querySelectorAll(".dropdown-toggle").forEach((toggle) => {
    toggle.addEventListener("click", function (e) {
      e.stopPropagation();
      const dropdown = this.closest(".dropdown");
      dropdown.classList.toggle("active");
    });
  });

  // Close on outside click
  document.addEventListener("click", () => {
    document.querySelectorAll(".dropdown.active").forEach((dropdown) => {
      dropdown.classList.remove("active");
    });
  });
}

// =====================================================
// Search & Filters
// =====================================================
function initSearchFilters() {
  const searchForm = document.querySelector(".search-box");
  if (!searchForm) return;

  const searchInput = searchForm.querySelector(".search-input");
  const categorySelect = searchForm.querySelector('select[name="category"]');
  const sortSelect = searchForm.querySelector('select[name="sort"]');

  // Quick filters
  document.querySelectorAll(".quick-filter").forEach((filter) => {
    filter.addEventListener("click", function () {
      document
        .querySelectorAll(".quick-filter")
        .forEach((f) => f.classList.remove("active"));
      this.classList.add("active");

      // Update search based on filter
      const filterType = this.dataset.filter;
      if (categorySelect && filterType) {
        categorySelect.value = filterType;
      }

      // Trigger search
      if (searchForm) {
        searchForm.dispatchEvent(new Event("submit"));
      }
    });
  });
}

// =====================================================
// Job Cards Interaction
// =====================================================
function initJobCards() {
  // Save job functionality
  document.querySelectorAll(".btn-save-job").forEach((btn) => {
    btn.addEventListener("click", function (e) {
      e.preventDefault();
      e.stopPropagation();

      const jobId = this.dataset.jobId;
      const icon = this.querySelector("i");

      // Toggle saved state
      this.classList.toggle("saved");
      if (this.classList.contains("saved")) {
        icon.classList.remove("far");
        icon.classList.add("fas");
        showToast("Job saved successfully!", "success");
      } else {
        icon.classList.remove("fas");
        icon.classList.add("far");
        showToast("Job removed from saved", "info");
      }

      // Send AJAX request
      saveJob(jobId, this.classList.contains("saved"));
    });
  });
}

async function saveJob(jobId, isSaved) {
  try {
    const response = await fetch(`${BASE_URL}/api/save-job.php`, {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
      },
      body: JSON.stringify({ job_id: jobId, save: isSaved }),
    });

    const data = await response.json();
    if (!data.success) {
      console.error("Failed to save job");
    }
  } catch (error) {
    console.error("Error saving job:", error);
  }
}

// =====================================================
// Tabs
// =====================================================
function initTabs() {
  document.querySelectorAll(".tabs").forEach((tabContainer) => {
    const tabs = tabContainer.querySelectorAll(".tab");
    const panels =
      tabContainer.closest(".tab-wrapper")?.querySelectorAll(".tab-panel") ||
      document.querySelectorAll(
        `[data-tab-group="${tabContainer.dataset.tabGroup}"] .tab-panel`
      );

    tabs.forEach((tab) => {
      tab.addEventListener("click", () => {
        // Update active tab
        tabs.forEach((t) => t.classList.remove("active"));
        tab.classList.add("active");

        // Show corresponding panel
        const targetId = tab.dataset.tab;
        panels.forEach((panel) => {
          panel.classList.toggle("active", panel.id === targetId);
        });
      });
    });
  });
}

// =====================================================
// AJAX Helper
// =====================================================
async function fetchAPI(url, options = {}) {
  try {
    const defaultOptions = {
      headers: {
        "Content-Type": "application/json",
        "X-Requested-With": "XMLHttpRequest",
      },
    };

    const response = await fetch(url, { ...defaultOptions, ...options });
    const data = await response.json();

    if (!response.ok) {
      throw new Error(data.message || "Request failed");
    }

    return data;
  } catch (error) {
    console.error("API Error:", error);
    throw error;
  }
}

// =====================================================
// File Upload Preview
// =====================================================
function initFileUpload() {
  document.querySelectorAll(".file-upload-input").forEach((input) => {
    input.addEventListener("change", function () {
      const preview =
        this.closest(".file-upload").querySelector(".file-preview");
      const fileName = this.closest(".file-upload").querySelector(".file-name");

      if (this.files && this.files[0]) {
        const file = this.files[0];

        // Update filename display
        if (fileName) {
          fileName.textContent = file.name;
        }

        // Image preview
        if (preview && file.type.startsWith("image/")) {
          const reader = new FileReader();
          reader.onload = (e) => {
            preview.src = e.target.result;
            preview.style.display = "block";
          };
          reader.readAsDataURL(file);
        }
      }
    });
  });
}

// =====================================================
// Dynamic Form Fields
// =====================================================
function addFormField(containerId, template) {
  const container = document.getElementById(containerId);
  if (!container) return;

  const index = container.children.length;
  const newField = document.createElement("div");
  newField.className = "form-field-item";
  newField.innerHTML = template.replace(/\{index\}/g, index);

  container.appendChild(newField);

  // Focus first input
  const firstInput = newField.querySelector("input, textarea, select");
  if (firstInput) firstInput.focus();
}

function removeFormField(button) {
  const field = button.closest(".form-field-item");
  if (field) {
    field.remove();
  }
}

// =====================================================
// Debounce Utility
// =====================================================
function debounce(func, wait) {
  let timeout;
  return function executedFunction(...args) {
    const later = () => {
      clearTimeout(timeout);
      func(...args);
    };
    clearTimeout(timeout);
    timeout = setTimeout(later, wait);
  };
}

// =====================================================
// Format Utilities
// =====================================================
function formatCurrency(amount, currency = "USD") {
  return new Intl.NumberFormat("en-US", {
    style: "currency",
    currency: currency,
    maximumFractionDigits: 0,
  }).format(amount);
}

function formatDate(dateString, options = {}) {
  const defaultOptions = { year: "numeric", month: "short", day: "numeric" };
  return new Date(dateString).toLocaleDateString("en-US", {
    ...defaultOptions,
    ...options,
  });
}

function timeAgo(dateString) {
  const now = new Date();
  const date = new Date(dateString);
  const diff = Math.floor((now - date) / 1000);

  if (diff < 60) return "Just now";
  if (diff < 3600) return `${Math.floor(diff / 60)} min ago`;
  if (diff < 86400) return `${Math.floor(diff / 3600)} hours ago`;
  if (diff < 604800) return `${Math.floor(diff / 86400)} days ago`;

  return formatDate(dateString);
}

// =====================================================
// Confirmation Dialog
// =====================================================
function confirmAction(message, callback) {
  const modal = document.getElementById("confirmModal");
  if (!modal) {
    if (confirm(message)) {
      callback();
    }
    return;
  }

  const messageEl = modal.querySelector(".confirm-message");
  const confirmBtn = modal.querySelector(".btn-confirm");

  if (messageEl) messageEl.textContent = message;

  openModal("confirmModal");

  // Handle confirm
  const handleConfirm = () => {
    closeModal("confirmModal");
    callback();
    confirmBtn.removeEventListener("click", handleConfirm);
  };

  confirmBtn.addEventListener("click", handleConfirm);
}

// =====================================================
// Loading States
// =====================================================
function setLoading(element, isLoading) {
  if (isLoading) {
    element.classList.add("loading");
    element.disabled = true;
    element.dataset.originalText = element.innerHTML;
    element.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Loading...';
  } else {
    element.classList.remove("loading");
    element.disabled = false;
    element.innerHTML = element.dataset.originalText;
  }
}

// =====================================================
// Global Variables
// =====================================================
const BASE_URL = window.location.origin + "/job-portal";
