/**
 * ==============================================
 * MODERN MODULAR FRAMEWORK - MAIN JAVASCRIPT
 * Organized modular JavaScript for modern websites
 * ==============================================
 */

'use strict';

/* Developer note:
 * This file assumes a browser DOM (document/window). It is not suitable
 * for direct `require()` in Node without a DOM shim (JSDOM) because it
 * reads elements like `.header` and attaches event listeners. To test
 * this file automatically consider headless browser tests or a small
 * harness that mounts a minimal DOM.
 */

/**
 * NAVIGATION MODULE
 */
const Navigation = {
    init() {
        // Initialize navigation-related behaviors. Keep these modular so
        // the logic is testable and each feature (smooth scroll, active
        // link highlighting, mobile menu) can be adjusted independently.
        this.setupSmoothScrolling();
        this.setupActiveStates();
        this.setupMobileMenu();
        this.setupScrollHeader();
        console.log('✅ Navigation module initialized');
    },

    setupSmoothScrolling() {
    // Attach a smooth scroll handler to internal anchor links.
    // We calculate an offset using the header height so anchored
    // sections are visible below the sticky header. Important:
    // - We subtract an extra 20px to provide breathing room so the
    //   anchored element isn't flush with the header.
    // - Keep this in sync with `scroll-padding-top` in CSS.
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function(e) {
                e.preventDefault();
                const targetId = this.getAttribute('href');
                const targetElement = document.querySelector(targetId);
                
                if (targetElement) {
                    const headerHeight = document.querySelector('.header').offsetHeight;
                    const targetPosition = targetElement.offsetTop - headerHeight - 20;
                    
                    window.scrollTo({
                        top: targetPosition,
                        behavior: 'smooth'
                    });
                }
            });
        });
    },

    setupActiveStates() {
        const sections = document.querySelectorAll('section[id]');
        const navLinks = document.querySelectorAll('.nav-link');

        if (sections.length === 0 || navLinks.length === 0) return;

        // Prefer IntersectionObserver for efficient active-link updates.
        // Use IntersectionObserver where available for efficient
        // visibility detection. We use a negative top/bottom rootMargin so
        // the observed intersection occurs when the section is roughly
        // centered in the viewport. If you change this tuning you may
        // also want to adjust the CSS `nav-link.active` styling to match.
    if ('IntersectionObserver' in window) {
            const observerOptions = {
                root: null,
                rootMargin: '-40% 0px -40% 0px',
                threshold: 0
            };

            const io = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (!entry.target.id) return;
                    if (entry.isIntersecting) {
                        const id = entry.target.id;
                        navLinks.forEach(link => {
                            link.classList.toggle('active', link.getAttribute('href') === `#${id}`);
                        });
                    }
                });
            }, observerOptions);

            sections.forEach(s => io.observe(s));

            // ensure at least one link is active on load (handles direct
            // links with hashes and initial page position)
            const onLoadCheck = () => {
                const hash = window.location.hash;
                if (hash) {
                    navLinks.forEach(link => link.classList.toggle('active', link.getAttribute('href') === hash));
                } else {
                    // pick the first visible section
                    sections.forEach(section => {
                        const rect = section.getBoundingClientRect();
                        if (rect.top <= window.innerHeight * 0.5 && rect.bottom >= window.innerHeight * 0.25) {
                            const id = section.id;
                            navLinks.forEach(link => link.classList.toggle('active', link.getAttribute('href') === `#${id}`));
                        }
                    });
                }
            };

            window.addEventListener('load', onLoadCheck);
            window.addEventListener('hashchange', onLoadCheck);
        } else {
            // Fallback: throttled scroll detection (less efficient but
            // compatible with older environments). Throttling avoids
            // flooding the main thread with layout reads.
            const updateActiveStates = Utils.throttle(() => {
                let current = '';
                const scrollPosition = window.pageYOffset;
                const headerHeight = document.querySelector('.header').offsetHeight;

                sections.forEach(section => {
                    const sectionTop = section.offsetTop - headerHeight - 50;
                    const sectionBottom = sectionTop + section.offsetHeight;
                    if (scrollPosition >= sectionTop && scrollPosition < sectionBottom) {
                        current = section.getAttribute('id');
                    }
                });

                navLinks.forEach(link => {
                    link.classList.remove('active');
                    if (link.getAttribute('href') === `#${current}`) {
                        link.classList.add('active');
                    }
                });
            }, 100);

            window.addEventListener('scroll', updateActiveStates);
            updateActiveStates();
        }
    },

    setupMobileMenu() {
        const navbar = document.querySelector('.navbar');
        const navMenu = document.querySelector('.nav-menu');
        
        if (!navbar || !navMenu) return;

    // Create a simple mobile menu toggle button. We inject this
    // only when the window width is below the breakpoint to keep
    // desktop DOM light and avoid duplicate UI elements.
    const mobileMenuBtn = document.createElement('button');
        mobileMenuBtn.className = 'mobile-menu-btn';
        mobileMenuBtn.innerHTML = '☰';
        mobileMenuBtn.setAttribute('aria-label', 'Toggle mobile menu');
        
        const checkMobile = () => {
            if (window.innerWidth <= 768) {
                if (!navbar.querySelector('.mobile-menu-btn')) {
                    navbar.insertBefore(mobileMenuBtn, navMenu);
                }
            } else {
                const existingBtn = navbar.querySelector('.mobile-menu-btn');
                if (existingBtn) {
                    existingBtn.remove();
                }
                navMenu.classList.remove('nav-menu-open');
            }
        };

        mobileMenuBtn.addEventListener('click', () => {
            navMenu.classList.toggle('nav-menu-open');
            mobileMenuBtn.innerHTML = navMenu.classList.contains('nav-menu-open') ? '✕' : '☰';
        });

        document.addEventListener('click', (e) => {
            if (!navbar.contains(e.target)) {
                navMenu.classList.remove('nav-menu-open');
                mobileMenuBtn.innerHTML = '☰';
            }
        });

        navMenu.addEventListener('click', (e) => {
            if (e.target.classList.contains('nav-link')) {
                navMenu.classList.remove('nav-menu-open');
                mobileMenuBtn.innerHTML = '☰';
            }
        });

        checkMobile();
        window.addEventListener('resize', Utils.debounce(checkMobile, 250));
    },

    setupScrollHeader() {
        const header = document.querySelector('.header');
        if (!header) return;

        // Add a small visual change once the user scrolls to give the
        // header depth (shadow + background). Throttle to improve
        // performance during scrolling.
        const updateHeaderOnScroll = Utils.throttle(() => {
            if (window.scrollY > 50) {
                header.classList.add('header-scrolled');
            } else {
                header.classList.remove('header-scrolled');
            }
        }, 100);

        window.addEventListener('scroll', updateHeaderOnScroll);
    }
};

/**
 * THEME MANAGER MODULE
 */
const ThemeManager = {
    currentTheme: 'light',

    init() {
    // Initialize theme using the user's system preference. This
    // project intentionally follows 'prefers-color-scheme' rather
    // than storing a user override in localStorage. Rationale:
    // - Keeps the site consistent with OS-level dark/light mode.
    // - Avoids storing UI state that may conflict with system
    //   accessibility settings. If a future requirement needs a
    //   persistent user toggle, implement a small preference
    //   control that writes to localStorage and call applyTheme().
        this.applySystemTheme();
        // Watch for changes to the system preference and update live.
        this.watchSystemPreference();
        console.log('✅ Theme manager initialized');
    },

    applySystemTheme() {
        // Read system preference and apply it site-wide by setting
        // the `data-theme` attribute on the HTML element. CSS contains
        // selectors keyed on [data-theme="dark"] to alter colors.
        const systemPreference = window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
        this.currentTheme = systemPreference;
        this.applyTheme(this.currentTheme);
    },

    applyTheme(theme) {
        // Apply the theme by setting the data attribute. CSS should
        // reference this attribute to conditionally change colors and
        // backgrounds. We also dispatch a custom event so other modules
        // can react to theme changes if needed.
        document.documentElement.setAttribute('data-theme', theme);
        this.currentTheme = theme;

        window.dispatchEvent(new CustomEvent('themeChanged', { detail: { theme } }));
    },

    // Theme follows system preference by default. No programmatic setThemeMode retained.

    // No UI toggle: theme follows the system preference only.

    watchSystemPreference() {
        const mediaQuery = window.matchMedia('(prefers-color-scheme: dark)');
        mediaQuery.addEventListener('change', (e) => {
            const newTheme = e.matches ? 'dark' : 'light';
            this.applyTheme(newTheme);
        });
    }
};

/**
 * ANIMATION MANAGER MODULE
 */
const AnimationManager = {
    observers: [],

    init() {
        // Set up progressive-enhancement animations. We observe
        // elements as they enter the viewport and add classes to
        // trigger CSS animations. This ensures animations don't run
        // on elements that are off-screen and improves perceived perf.
        this.setupScrollAnimations();
        this.setupHoverEffects();
        this.setupParallaxEffects();
        console.log('✅ Animation manager initialized');
    },

    setupScrollAnimations() {
        // IntersectionObserver is used to trigger entrance animations
        // when elements are in view. The rootMargin nudges the trigger
        // point slightly so animations feel natural.
        const observerOptions = {
            threshold: 0.1,
            rootMargin: '0px 0px -50px 0px'
        };

        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('animate-in');
                }
            });
        }, observerOptions);

        const animatableElements = document.querySelectorAll('.card, .section-header, .hero');
        animatableElements.forEach(element => {
            element.classList.add('animate-ready');
            observer.observe(element);
        });

        this.observers.push(observer);
    },

    setupHoverEffects() {
        // Button and card hover interactions. These are purely
        // cosmetic but provide a tactile feel to the UI. We limit
        // the work inside event handlers to small style changes
        // to avoid layout thrashing.
        document.querySelectorAll('.btn').forEach(button => {
            button.addEventListener('mouseenter', function() {
                this.style.transform = 'translateY(-2px) scale(1.02)';
            });
            
            button.addEventListener('mouseleave', function() {
                this.style.transform = 'translateY(0) scale(1)';
            });

            button.addEventListener('click', function(e) {
                const ripple = document.createElement('span');
                ripple.className = 'ripple';
                
                const rect = this.getBoundingClientRect();
                const size = Math.max(rect.width, rect.height);
                const x = e.clientX - rect.left - size / 2;
                const y = e.clientY - rect.top - size / 2;
                
                ripple.style.width = ripple.style.height = size + 'px';
                ripple.style.left = x + 'px';
                ripple.style.top = y + 'px';
                
                this.appendChild(ripple);
                
                setTimeout(() => {
                    ripple.remove();
                }, 600);
            });
        });

        document.querySelectorAll('.card').forEach(card => {
            card.addEventListener('mouseenter', function() {
                this.style.transform = 'translateY(-8px) rotateX(2deg)';
            });
            
            card.addEventListener('mouseleave', function() {
                this.style.transform = 'translateY(0) rotateX(0deg)';
            });
        });
    },

    setupParallaxEffects() {
        const parallaxElements = document.querySelectorAll('.hero');
        
        if (parallaxElements.length === 0) return;

        const updateParallax = Utils.throttle(() => {
            const scrolled = window.pageYOffset;
            
            parallaxElements.forEach(element => {
                const speed = 0.5;
                const yPos = -(scrolled * speed);
                element.style.transform = `translateY(${yPos}px)`;
            });
        }, 16);

        window.addEventListener('scroll', updateParallax);
    },

    destroy() {
        this.observers.forEach(observer => {
            observer.disconnect();
        });
        this.observers = [];
    }
};

/**
 * FORM HANDLER MODULE
 */
const FormHandler = {
    init() {
        // Initialize form helpers: inline validation and graceful
        // submission handling. Keeping this separate allows us to
        // reuse validators across multiple form elements.
        this.setupFormValidation();
        this.setupFormSubmission();
        console.log('✅ Form handler initialized');
    },

    setupFormValidation() {
        const forms = document.querySelectorAll('form');
        
        forms.forEach(form => {
            const inputs = form.querySelectorAll('input, textarea, select');
            
            inputs.forEach(input => {
                input.addEventListener('blur', () => {
                    this.validateField(input);
                });
                
                input.addEventListener('input', () => {
                    this.clearFieldError(input);
                });
            });
            
            form.addEventListener('submit', (e) => {
                if (!this.validateForm(form)) {
                    e.preventDefault();
                }
            });
        });
    },

    validateField(field) {
        const value = field.value.trim();
        const fieldType = field.type;
        const isRequired = field.hasAttribute('required');
        
        let isValid = true;
        let errorMessage = '';

        if (isRequired && !value) {
            isValid = false;
            errorMessage = 'This field is required';
        }
        else if (fieldType === 'email' && value) {
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(value)) {
                isValid = false;
                errorMessage = 'Please enter a valid email address';
            }
        }
        else if (fieldType === 'tel' && value) {
            const phoneRegex = /^[\+]?[1-9][\d]{0,15}$/;
            if (!phoneRegex.test(value.replace(/[\s\-\(\)]/g, ''))) {
                isValid = false;
                errorMessage = 'Please enter a valid phone number';
            }
        }

        if (!isValid) {
            this.showFieldError(field, errorMessage);
        } else {
            this.clearFieldError(field);
        }

        return isValid;
    },

    validateForm(form) {
        const fields = form.querySelectorAll('input, textarea, select');
        let isFormValid = true;

        fields.forEach(field => {
            if (!this.validateField(field)) {
                isFormValid = false;
            }
        });

        return isFormValid;
    },

    showFieldError(field, message) {
        field.classList.add('field-error');
        
        let errorElement = field.parentNode.querySelector('.field-error-message');
        if (!errorElement) {
            errorElement = document.createElement('div');
            errorElement.className = 'field-error-message';
            field.parentNode.appendChild(errorElement);
        }
        
        errorElement.textContent = message;
    },

    clearFieldError(field) {
        field.classList.remove('field-error');
        const errorElement = field.parentNode.querySelector('.field-error-message');
        if (errorElement) {
            errorElement.remove();
        }
    },

    setupFormSubmission() {
        document.querySelectorAll('form').forEach(form => {
            // Allow forms to opt-out of AJAX interception by adding
            // data-no-ajax="1" or class="no-ajax". This keeps the
            // reservation flow identical to the server-side handler (a
            // normal POST) while still providing enhanced UX for other forms.
            if (form.getAttribute('data-no-ajax') === '1' || form.classList.contains('no-ajax')) return;

            form.addEventListener('submit', async (e) => {
                e.preventDefault();

                if (!this.validateForm(form)) {
                    return;
                }

                const submitBtn = form.querySelector('button[type="submit"]');
                const originalText = submitBtn.textContent;
                
                submitBtn.textContent = 'Sending...';
                submitBtn.disabled = true;

                try {
                    const result = await this.submitForm(form);
                    this.showSuccessMessage(form, (result && result.message) ? result.message : null);
                    form.reset();
                } catch (error) {
                    this.showErrorMessage(form, error && error.message ? error.message : 'Something went wrong. Please try again.');
                } finally {
                    submitBtn.textContent = originalText;
                    submitBtn.disabled = false;
                }
            });
        });
    },

    async submitForm(form) {
        const formData = new FormData(form);

        const response = await fetch('/contact.php', {
            method: 'POST',
            body: formData,
            credentials: 'same-origin',
            headers: {
                'Accept': 'application/json'
            }
        });

        const text = await response.text();
        let data = null;
        try {
            data = text ? JSON.parse(text) : null;
        } catch (err) {
            // not JSON
            data = null;
        }

        if (!response.ok) {
            const serverErr = data && data.errors ? (Array.isArray(data.errors) ? data.errors.join('; ') : data.errors) : (data && data.message) || 'Server error';
            throw new Error(serverErr);
        }

        if (data && data.success) {
            return data;
        }

        const errMsg = data && data.errors ? (Array.isArray(data.errors) ? data.errors.join('; ') : data.errors) : (data && data.message) || 'Submission failed';
        throw new Error(errMsg);
    },

    showSuccessMessage(form, serverMessage) {
        const message = document.createElement('div');
        message.className = 'form-message form-success';
        message.textContent = serverMessage || 'Thank you! Your message has been sent successfully.';
        form.parentNode.insertBefore(message, form.nextSibling);

        // Make message focusable and bring into view
        message.setAttribute('tabindex', '-1');
        message.scrollIntoView({ behavior: 'smooth', block: 'center' });
        message.focus({ preventScroll: true });

        setTimeout(() => {
            // remove focus then remove message
            try { message.blur(); } catch (e) {}
            message.remove();
        }, 5000);
    },

    showErrorMessage(form, errorText) {
        const message = document.createElement('div');
        message.className = 'form-message form-error';
        message.textContent = errorText;
        form.parentNode.insertBefore(message, form.nextSibling);

        message.setAttribute('tabindex', '-1');
        message.scrollIntoView({ behavior: 'smooth', block: 'center' });
        message.focus({ preventScroll: true });

        setTimeout(() => {
            try { message.blur(); } catch (e) {}
            message.remove();
        }, 5000);
    }
};

/**
 * PERFORMANCE MONITOR MODULE
 */
const PerformanceMonitor = {
    init() {
        this.monitorPageLoad();
        this.setupLazyLoading();
        console.log('✅ Performance monitor initialized');
    },

    monitorPageLoad() {
        window.addEventListener('load', () => {
            const perfData = performance.getEntriesByType('navigation')[0];
            const loadTime = perfData.loadEventEnd - perfData.loadEventStart;
            console.log(`📊 Page loaded in ${loadTime}ms`);
        });
    },

    setupLazyLoading() {
        const images = document.querySelectorAll('img[data-src]');
        
        if (images.length === 0) return;

        const imageObserver = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const img = entry.target;
                    img.src = img.dataset.src;
                    img.removeAttribute('data-src');
                    img.classList.add('loaded');
                    imageObserver.unobserve(img);
                }
            });
        }, {
            rootMargin: '50px'
        });

        images.forEach(img => {
            imageObserver.observe(img);
        });
    }
};

/**
 * UTILITY MODULE
 */
const Utils = {
    debounce(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    },

    throttle(func, limit) {
        let inThrottle;
        return function() {
            const args = arguments;
            const context = this;
            if (!inThrottle) {
                func.apply(context, args);
                inThrottle = true;
                setTimeout(() => inThrottle = false, limit);
            }
        };
    },

    createEventEmitter() {
        const events = {};
        return {
            on(event, callback) {
                if (!events[event]) events[event] = [];
                events[event].push(callback);
            },
            off(event, callback) {
                if (events[event]) {
                    events[event] = events[event].filter(cb => cb !== callback);
                }
            },
            emit(event, data) {
                if (events[event]) {
                    events[event].forEach(callback => callback(data));
                }
            }
        };
    },

    scrollToElement(element, offset = 0) {
        if (typeof element === 'string') {
            element = document.querySelector(element);
        }
        
        if (!element) return;

        const targetPosition = element.offsetTop - offset;
        
        window.scrollTo({
            top: targetPosition,
            behavior: 'smooth'
        });
    },

    getViewport() {
        return {
            width: window.innerWidth || document.documentElement.clientWidth,
            height: window.innerHeight || document.documentElement.clientHeight
        };
    },

    isInViewport(element) {
        const rect = element.getBoundingClientRect();
        const viewport = this.getViewport();
        
        return (
            rect.top >= 0 &&
            rect.left >= 0 &&
            rect.bottom <= viewport.height &&
            rect.right <= viewport.width
        );
    },

    formatNumber(num) {
        return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ',');
    },

    generateId(prefix = 'id') {
        return `${prefix}-${Date.now()}-${Math.random().toString(36).substr(2, 9)}`;
    }
};

/**
 * STEPPER MODULE
 * Provides accessible increment/decrement buttons for number inputs.
 * - Works with markup: <div class="stepper"><button class="stepper-btn" data-step="down">-</button><input type="number">... </div>
 * - Buttons must have data-step="up" or data-step="down". Module preserves min/max/step.
 */
const Stepper = {
    init() {
        // Click handling (delegated)
        document.addEventListener('click', (e) => {
            const btn = e.target.closest('.stepper-btn');
            if (!btn) return;
            e.preventDefault();
            this._handleButton(btn);
        });

        // Keyboard support for buttons (Enter/Space) and inputs (Arrow keys)
        document.addEventListener('keydown', (e) => {
            const btn = e.target.closest('.stepper-btn');
            if (btn && (e.key === 'Enter' || e.key === ' ')) {
                e.preventDefault();
                this._handleButton(btn);
                return;
            }

            const input = e.target.closest('.stepper input[type="number"], input[type="number"][data-stepper]');
            if (!input) return;

            if (e.key === 'ArrowUp' || e.key === 'ArrowDown' || e.key === 'PageUp' || e.key === 'PageDown') {
                e.preventDefault();
                const multiplier = (e.key === 'PageUp' || e.key === 'PageDown') ? 10 : 1;
                const dir = (e.key === 'ArrowUp' || e.key === 'PageUp') ? 1 : -1;
                this._changeValue(input, dir * multiplier);
            }
        });
    },

    _handleButton(btn) {
        const wrapper = btn.closest('.stepper');
        if (!wrapper) return;
        const input = wrapper.querySelector('input[type="number"]');
        if (!input) return;
        const stepDir = (btn.dataset.step === 'up') ? 1 : -1;
        this._changeValue(input, stepDir);
        input.focus();
    },

    _changeValue(input, dir) {
        const stepAttr = input.getAttribute('step');
        const step = stepAttr ? parseFloat(stepAttr) : 1;
        if (isNaN(step) || step <= 0) {
            // fallback
            step = 1;
        }
        const delta = dir * step;
        const min = input.hasAttribute('min') ? parseFloat(input.getAttribute('min')) : -Infinity;
        const max = input.hasAttribute('max') ? parseFloat(input.getAttribute('max')) : Infinity;

        let current = parseFloat(input.value);
        if (isNaN(current)) current = 0;

        let next = current + delta;
        // Clamp to min/max
        if (!isNaN(min) && next < min) next = min;
        if (!isNaN(max) && next > max) next = max;

        // Maintain integer precision when step is integer
        if (Number.isInteger(step)) next = Math.round(next);

        input.value = next;
        // Dispatch input event so other handlers react
        input.dispatchEvent(new Event('input', { bubbles: true }));
        input.dispatchEvent(new Event('change', { bubbles: true }));
    }
};

/**
 * PositionAgeRules
 * Adjusts the minimum age required based on selected position.
 * - Server and Bartender require minimum age 18.
 * - Other positions minimum age remains 16.
 */
const PositionAgeRules = {
    init() {
        this.positionEl = document.getElementById('position-desired');
        this.ageInput = document.getElementById('age');
        this.ageNote = document.getElementById('age-note');
        if (!this.positionEl || !this.ageInput) return;
        this.positionEl.addEventListener('change', () => this.applyRule());
        this.applyRule();
    },

    applyRule() {
        const pos = (this.positionEl.value || '').toLowerCase();
        const requires18 = (pos === 'server' || pos === 'bartender');
        if (requires18) {
            this.ageInput.min = '18';
            if (this.ageInput.value && Number(this.ageInput.value) < 18) {
                this.ageInput.value = 18;
            }
            if (this.ageNote) this.ageNote.textContent = 'Minimum age to apply for this position is 18 years.';
        } else {
            this.ageInput.min = '16';
            if (this.ageNote) this.ageNote.textContent = 'Minimum age to apply is 16 years.';
        }
    }
};

/**
 * APPLICATION INITIALIZATION
 */
document.addEventListener('DOMContentLoaded', function() {
    try {
        Navigation.init();
        ThemeManager.init();
        AnimationManager.init();
            FormHandler.init();
            Stepper.init();
            PositionAgeRules.init();
        PerformanceMonitor.init();

        console.log('🚀 Modern Modular Framework initialized successfully!');
        console.log(`📱 Viewport: ${Utils.getViewport().width}x${Utils.getViewport().height}`);
        
        if (window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1') {
            window.ModularFramework = {
                Navigation,
                ThemeManager,
                AnimationManager,
                FormHandler,
                PerformanceMonitor,
                Utils
            };
            console.log('🔧 Development mode: Framework modules exposed to window.ModularFramework');
        }

        window.dispatchEvent(new CustomEvent('frameworkReady', {
            detail: { timestamp: Date.now() }
        }));

        
        
    } catch (error) {
        console.error('❌ Framework initialization failed:', error);
    }
});

window.addEventListener('resize', Utils.debounce(() => {
    console.log(`📱 Window resized: ${Utils.getViewport().width}x${Utils.getViewport().height}`);
    
    window.dispatchEvent(new CustomEvent('viewportChanged', {
        detail: Utils.getViewport()
    }));
}, 250));

document.addEventListener('visibilitychange', () => {
    if (document.hidden) {
        console.log('👁️ Page hidden');
    } else {
        console.log('👁️ Page visible');
    }
});

window.addEventListener('online', () => {
    console.log('🌐 Connection restored');
});

window.addEventListener('offline', () => {
    console.log('📴 Connection lost');
});