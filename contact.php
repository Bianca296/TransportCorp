<?php
/**
 * Contact Page
 */

// Configuration
require_once 'config/config.php';
require_once 'config/email.php';

// PHPMailer classes loaded manually

// Check if PHPMailer is available
$phpmailer_available = file_exists(__DIR__ . '/vendor/autoload.php') || 
                      file_exists(__DIR__ . '/includes/PHPMailer/src/PHPMailer.php');

$error_message = '';
$success_message = '';
$form_data = [];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF Protection
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error_message = 'Security token mismatch. Please try again.';
    } else {
        // Validate form data
        $form_data = [
            'name' => trim($_POST['name'] ?? ''),
            'email' => trim($_POST['email'] ?? ''),
            'phone' => trim($_POST['phone'] ?? ''),
            'subject' => trim($_POST['subject'] ?? ''),
            'service_type' => $_POST['service_type'] ?? 'general',
            'message' => trim($_POST['message'] ?? '')
        ];
        
        // Validation
        $errors = [];
        
        if (empty($form_data['name']) || strlen($form_data['name']) < 2) {
            $errors[] = 'Name must be at least 2 characters long.';
        }
        
        if (empty($form_data['email']) || !filter_var($form_data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Please provide a valid email address.';
        }
        
        if (!empty($form_data['phone']) && !preg_match('/^[\+]?[0-9\s\-\(\)]{10,}$/', $form_data['phone'])) {
            $errors[] = 'Please provide a valid phone number.';
        }
        
        if (empty($form_data['subject']) || strlen($form_data['subject']) < 5) {
            $errors[] = 'Subject must be at least 5 characters long.';
        }
        
        if (empty($form_data['message']) || strlen($form_data['message']) < 10) {
            $errors[] = 'Message must be at least 10 characters long.';
        }
        
        // Check for spam (basic protection)
        $spam_keywords = ['viagra', 'casino', 'lottery', 'winner', 'congratulations'];
        $message_lower = strtolower($form_data['message']);
        foreach ($spam_keywords as $keyword) {
            if (strpos($message_lower, $keyword) !== false) {
                $errors[] = 'Your message appears to contain spam content.';
                break;
            }
        }
        
        if (!empty($errors)) {
            $error_message = 'Please fix the following errors:<br>• ' . implode('<br>• ', $errors);
        } else {
            // Try to send email if PHPMailer is available
            if ($phpmailer_available) {
                $email_result = sendContactEmail($form_data);
                if ($email_result['success']) {
                    $success_message = 'Thank you for your message! We\'ll get back to you within 24 hours.';
                    // Clear form data on success
                    $form_data = [];
                    // Log the contact to database
                    logContactSubmission($form_data);
                } else {
                    $error_message = 'Sorry, there was an error sending your message. Please try again or contact us directly.';
                    error_log("Contact form email error: " . $email_result['error']);
                }
            } else {
                // Fallback: Save to database and show message
                logContactSubmission($form_data);
                $success_message = 'Thank you for your message! We have received your inquiry and will get back to you soon.';
                $form_data = [];
            }
        }
    }
}

// Generate CSRF token
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Send contact email using PHPMailer
function sendContactEmail($data) {
    try {
        // Load PHPMailer based on installation method
        if (file_exists(__DIR__ . '/vendor/autoload.php')) {
            // Composer installation
            require_once __DIR__ . '/vendor/autoload.php';
            $mail = new PHPMailer\PHPMailer\PHPMailer(true);
        } else {
            // Manual installation
            require_once __DIR__ . '/includes/PHPMailer/src/PHPMailer.php';
            require_once __DIR__ . '/includes/PHPMailer/src/SMTP.php';
            require_once __DIR__ . '/includes/PHPMailer/src/Exception.php';
            
            // Use full namespaced class names for manual installation
            $mail = new PHPMailer\PHPMailer\PHPMailer(true);
        }
        
        $config = EmailConfig::getSMTPConfig();
        
        // Server settings
        $mail->isSMTP();
        $mail->Host = $config['host'];
        $mail->SMTPAuth = true;
        $mail->Username = $config['username'];
        $mail->Password = $config['password'];
        $mail->SMTPSecure = $config['security'];
        $mail->Port = $config['port'];
        $mail->CharSet = 'UTF-8';
        
        // Recipients
        $mail->setFrom($config['from_email'], $config['from_name']);
        $mail->addAddress(EmailConfig::ADMIN_EMAIL, EmailConfig::ADMIN_NAME);
        $mail->addReplyTo($data['email'], $data['name']);
        
        // Content
        $email_template = EmailConfig::getContactEmailTemplate($data);
        $mail->isHTML(true);
        $mail->Subject = $email_template['subject'];
        $mail->Body = $email_template['body'];
        $mail->AltBody = $email_template['alt_body'];
        
        // Send main email
        $mail->send();
        
        // Send auto-reply if enabled
        if (EmailConfig::AUTO_REPLY_ENABLED) {
            // Create new PHPMailer instance for auto-reply
            $reply_mail = new PHPMailer\PHPMailer\PHPMailer(true);
            
            $reply_mail->isSMTP();
            $reply_mail->Host = $config['host'];
            $reply_mail->SMTPAuth = true;
            $reply_mail->Username = $config['username'];
            $reply_mail->Password = $config['password'];
            $reply_mail->SMTPSecure = $config['security'];
            $reply_mail->Port = $config['port'];
            $reply_mail->CharSet = 'UTF-8';
            
            $reply_mail->setFrom($config['from_email'], $config['from_name']);
            $reply_mail->addAddress($data['email'], $data['name']);
            
            $auto_reply = EmailConfig::getAutoReplyTemplate($data);
            $reply_mail->isHTML(true);
            $reply_mail->Subject = $auto_reply['subject'];
            $reply_mail->Body = $auto_reply['body'];
            $reply_mail->AltBody = $auto_reply['alt_body'];
            
            $reply_mail->send();
        }
        
        return ['success' => true];
        
    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

// Log contact submission to database
function logContactSubmission($data) {
    try {
        $database = new Database();
        $pdo = $database->getConnection();
        
        $sql = "INSERT INTO contact_submissions (name, email, phone, subject, service_type, message, ip_address, user_agent, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP)";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $data['name'] ?? '',
            $data['email'] ?? '',
            $data['phone'] ?? null,
            $data['subject'] ?? '',
            $data['service_type'] ?? 'General Inquiry',
            $data['message'] ?? '',
            $_SERVER['REMOTE_ADDR'] ?? null,
            $_SERVER['HTTP_USER_AGENT'] ?? null
        ]);
        
        return true;
    } catch (Exception $e) {
        error_log("Contact submission logging error: " . $e->getMessage());
        return false;
    }
}

// Set page title
$page_title = 'Contact Us - DAW Transport Company';

// Include header
include 'includes/header.php';
?>

<div class="container">
    <div class="contact-container">
        <!-- Page Header -->
        <div class="contact-header">
            <div class="contact-header-content">
                <h1>📧 Contact Us</h1>
                <p>Get in touch with our transport experts. We're here to help with all your shipping needs.</p>
            </div>
            <div class="contact-info-cards">
                <div class="info-card">
                    <div class="info-icon">📞</div>
                    <div class="info-content">
                        <h3>Phone</h3>
                        <p>(555) 123-4567</p>
                    </div>
                </div>
                <div class="info-card">
                    <div class="info-icon">📧</div>
                    <div class="info-content">
                        <h3>Email</h3>
                        <p>support@daw-transport.com</p>
                    </div>
                </div>
                <div class="info-card">
                    <div class="info-icon">🕒</div>
                    <div class="info-content">
                        <h3>Hours</h3>
                        <p>Mon-Fri: 8AM-6PM</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Success/Error Messages -->
        <?php if ($success_message): ?>
            <div class="alert alert-success">
                <span class="alert-icon">✅</span>
                <?php echo $success_message; ?>
            </div>
        <?php endif; ?>

        <?php if ($error_message): ?>
            <div class="alert alert-error">
                <span class="alert-icon">⚠️</span>
                <?php echo $error_message; ?>
            </div>
        <?php endif; ?>

        <?php if (!$phpmailer_available): ?>
            <div class="alert alert-warning">
                <span class="alert-icon">⚠️</span>
                <strong>Setup Required:</strong> PHPMailer is not installed. Your message will be saved but email notifications are disabled. 
                <a href="#setup-guide" style="color: #d68910; text-decoration: underline;">See setup guide below</a>.
            </div>
        <?php endif; ?>

        <div class="contact-content">
            <!-- Contact Form -->
            <div class="contact-form-section">
                <h2>Send us a Message</h2>
                <form method="POST" class="contact-form" id="contactForm">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="name">Full Name *</label>
                            <input type="text" 
                                   id="name" 
                                   name="name" 
                                   required 
                                   maxlength="100"
                                   value="<?php echo htmlspecialchars($form_data['name'] ?? ''); ?>"
                                   placeholder="Enter your full name">
                        </div>
                        <div class="form-group">
                            <label for="email">Email Address *</label>
                            <input type="email" 
                                   id="email" 
                                   name="email" 
                                   required 
                                   maxlength="255"
                                   value="<?php echo htmlspecialchars($form_data['email'] ?? ''); ?>"
                                   placeholder="your@email.com">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="phone">Phone Number</label>
                            <input type="tel" 
                                   id="phone" 
                                   name="phone" 
                                   maxlength="20"
                                   value="<?php echo htmlspecialchars($form_data['phone'] ?? ''); ?>"
                                   placeholder="(555) 123-4567">
                        </div>
                        <div class="form-group">
                            <label for="service_type">Service Interest</label>
                            <select id="service_type" name="service_type">
                                <option value="general" <?php echo ($form_data['service_type'] ?? '') === 'general' ? 'selected' : ''; ?>>General Inquiry</option>
                                <option value="land" <?php echo ($form_data['service_type'] ?? '') === 'land' ? 'selected' : ''; ?>>🚛 Land Transport</option>
                                <option value="air" <?php echo ($form_data['service_type'] ?? '') === 'air' ? 'selected' : ''; ?>>✈️ Air Transport</option>
                                <option value="ocean" <?php echo ($form_data['service_type'] ?? '') === 'ocean' ? 'selected' : ''; ?>>🚢 Ocean Transport</option>
                                <option value="quote" <?php echo ($form_data['service_type'] ?? '') === 'quote' ? 'selected' : ''; ?>>💰 Request Quote</option>
                                <option value="support" <?php echo ($form_data['service_type'] ?? '') === 'support' ? 'selected' : ''; ?>>🛠️ Customer Support</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="subject">Subject *</label>
                        <input type="text" 
                               id="subject" 
                               name="subject" 
                               required 
                               maxlength="200"
                               value="<?php echo htmlspecialchars($form_data['subject'] ?? ''); ?>"
                               placeholder="Brief description of your inquiry">
                    </div>
                    
                    <div class="form-group">
                        <label for="message">Message *</label>
                        <textarea id="message" 
                                  name="message" 
                                  required 
                                  rows="6" 
                                  maxlength="2000"
                                  placeholder="Please provide details about your transport needs, including pickup/delivery locations, package dimensions, timeline, and any special requirements..."><?php echo htmlspecialchars($form_data['message'] ?? ''); ?></textarea>
                        <div class="character-counter">
                            <span id="charCount">0</span> / 2000 characters
                        </div>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">
                            📧 Send Message
                        </button>
                        <button type="reset" class="btn btn-secondary">
                            🔄 Reset Form
                        </button>
                    </div>
                </form>
            </div>

            <!-- Contact Information Sidebar -->
            <div class="contact-info-section">
                <h2>Get in Touch</h2>
                
                <div class="contact-methods">
                    <div class="contact-method">
                        <div class="method-icon">📞</div>
                        <div class="method-content">
                            <h3>Call Us</h3>
                            <p>For immediate assistance</p>
                            <strong>(555) 123-4567</strong>
                        </div>
                    </div>
                    
                    <div class="contact-method">
                        <div class="method-icon">📧</div>
                        <div class="method-content">
                            <h3>Email Support</h3>
                            <p>For detailed inquiries</p>
                            <strong>support@daw-transport.com</strong>
                        </div>
                    </div>
                    
                    <div class="contact-method">
                        <div class="method-icon">💬</div>
                        <div class="method-content">
                            <h3>Live Chat</h3>
                            <p>Mon-Fri: 8AM-6PM</p>
                            <strong>Coming Soon</strong>
                        </div>
                    </div>
                </div>

                <div class="services-overview">
                    <h3>Our Services</h3>
                    <div class="service-list">
                        <div class="service-item">
                            <span class="service-icon">🚛</span>
                            <span>Land Transport</span>
                        </div>
                        <div class="service-item">
                            <span class="service-icon">✈️</span>
                            <span>Air Transport</span>
                        </div>
                        <div class="service-item">
                            <span class="service-icon">🚢</span>
                            <span>Ocean Transport</span>
                        </div>
                        <div class="service-item">
                            <span class="service-icon">📦</span>
                            <span>Package Tracking</span>
                        </div>
                        <div class="service-item">
                            <span class="service-icon">💰</span>
                            <span>Custom Quotes</span>
                        </div>
                        <div class="service-item">
                            <span class="service-icon">🔒</span>
                            <span>Secure Handling</span>
                        </div>
                    </div>
                </div>

                <div class="response-time">
                    <h3>Response Times</h3>
                    <ul>
                        <li><strong>Email:</strong> Within 24 hours</li>
                        <li><strong>Phone:</strong> Immediate during business hours</li>
                        <li><strong>Urgent matters:</strong> Same day response</li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- Setup Guide for PHPMailer -->
        <?php if (!$phpmailer_available): ?>
            <div id="setup-guide" class="setup-guide">
                <h2>📚 PHPMailer Setup Guide</h2>
                <div class="setup-content">
                    <p><strong>To enable email functionality, follow these steps:</strong></p>
                    
                    <div class="setup-step">
                        <h3>Step 1: Download PHPMailer</h3>
                        <p>Download PHPMailer from <a href="https://github.com/PHPMailer/PHPMailer" target="_blank">GitHub</a> and extract it to <code>/includes/PHPMailer/</code></p>
                        <div class="code-block">
                            <code>
                                DAW/<br>
                                └── includes/<br>
                                &nbsp;&nbsp;&nbsp;&nbsp;└── PHPMailer/<br>
                                &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;├── PHPMailer.php<br>
                                &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;├── SMTP.php<br>
                                &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;└── Exception.php
                            </code>
                        </div>
                    </div>
                    
                    <div class="setup-step">
                        <h3>Step 2: Configure Email Settings</h3>
                        <p>Edit <code>/config/email.php</code> with your SMTP settings:</p>
                        <div class="code-block">
                            <code>
                                const SMTP_HOST = 'your-smtp-server.com';<br>
                                const SMTP_USERNAME = 'your-email@domain.com';<br>
                                const SMTP_PASSWORD = 'your-password';<br>
                                const ADMIN_EMAIL = 'admin@your-domain.com';
                            </code>
                        </div>
                    </div>
                    
                    <div class="setup-step">
                        <h3>Step 3: Gmail Setup (if using Gmail)</h3>
                        <ul>
                            <li>Enable 2-Factor Authentication</li>
                            <li>Generate an App Password</li>
                            <li>Use App Password instead of regular password</li>
                            <li>Set SMTP_HOST to 'smtp.gmail.com'</li>
                        </ul>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
// Character counter for message textarea
document.addEventListener('DOMContentLoaded', function() {
    const messageTextarea = document.getElementById('message');
    const charCounter = document.getElementById('charCount');
    
    function updateCharCount() {
        const length = messageTextarea.value.length;
        charCounter.textContent = length;
        
        if (length > 1800) {
            charCounter.style.color = '#e74c3c';
        } else if (length > 1500) {
            charCounter.style.color = '#f39c12';
        } else {
            charCounter.style.color = '#3498db';
        }
    }
    
    messageTextarea.addEventListener('input', updateCharCount);
    updateCharCount(); // Initial count
    
    // Form validation
    const form = document.getElementById('contactForm');
    form.addEventListener('submit', function(e) {
        const name = document.getElementById('name').value.trim();
        const email = document.getElementById('email').value.trim();
        const subject = document.getElementById('subject').value.trim();
        const message = document.getElementById('message').value.trim();
        
        let errors = [];
        
        if (name.length < 2) errors.push('Name must be at least 2 characters long');
        if (!email.includes('@') || !email.includes('.')) errors.push('Please enter a valid email address');
        if (subject.length < 5) errors.push('Subject must be at least 5 characters long');
        if (message.length < 10) errors.push('Message must be at least 10 characters long');
        
        if (errors.length > 0) {
            e.preventDefault();
            alert('Please fix these errors:\n• ' + errors.join('\n• '));
        }
    });
    
    // Reset form handler
    form.addEventListener('reset', function() {
        setTimeout(updateCharCount, 10);
    });
});
</script>

<?php include 'includes/footer.php'; ?>
