<?php
/**
 * Email Configuration
 */
class EmailConfig {
    
    // SMTP Configuration
    const SMTP_HOST = 'smtp.gmail.com';          // Change to your SMTP server
    const SMTP_PORT = 587;                       // SMTP port (587 for TLS, 465 for SSL)
    const SMTP_SECURITY = 'tls';                 // 'tls' or 'ssl'
    const SMTP_USERNAME = 'your-email@gmail.com'; // Your email address
    const SMTP_PASSWORD = 'your-app-password';    // Your email password or app password
    
    // Sender Information
    const FROM_EMAIL = 'your-email@gmail.com';   // From email address
    const FROM_NAME = 'DAW Transport Company';   // From name
    const REPLY_TO_EMAIL = 'support@daw-transport.com'; // Reply-to email
    
    // Admin/Notification Settings
    const ADMIN_EMAIL = 'admin@daw-transport.com'; // Where contact forms are sent
    const ADMIN_NAME = 'DAW Admin';
    
    // Email Templates
    const CONTACT_SUBJECT_PREFIX = '[DAW Contact] ';
    const AUTO_REPLY_ENABLED = true;
    
    /**
     * Get SMTP configuration array
     * @return array SMTP settings
     */
    public static function getSMTPConfig() {
        return [
            'host' => self::SMTP_HOST,
            'port' => self::SMTP_PORT,
            'security' => self::SMTP_SECURITY,
            'username' => self::SMTP_USERNAME,
            'password' => self::SMTP_PASSWORD,
            'from_email' => self::FROM_EMAIL,
            'from_name' => self::FROM_NAME,
            'reply_to' => self::REPLY_TO_EMAIL
        ];
    }
    
    /**
     * Get contact form email template
     * @param array $data Form data
     * @return array Email content
     */
    public static function getContactEmailTemplate($data) {
        $subject = self::CONTACT_SUBJECT_PREFIX . $data['subject'];
        
        $body = "
        <html>
        <body style='font-family: Arial, sans-serif; line-height: 1.6; color: #333;'>
            <div style='max-width: 600px; margin: 0 auto; padding: 20px;'>
                <h2 style='color: #3498db; border-bottom: 2px solid #3498db; padding-bottom: 10px;'>
                    New Contact Form Submission
                </h2>
                
                <div style='background: #f8f9fa; padding: 20px; border-radius: 8px; margin: 20px 0;'>
                    <h3 style='margin-top: 0; color: #2c3e50;'>Contact Information</h3>
                    <p><strong>Name:</strong> " . htmlspecialchars($data['name']) . "</p>
                    <p><strong>Email:</strong> " . htmlspecialchars($data['email']) . "</p>
                    " . (!empty($data['phone']) ? "<p><strong>Phone:</strong> " . htmlspecialchars($data['phone']) . "</p>" : "") . "
                    <p><strong>Service Interest:</strong> " . htmlspecialchars($data['service_type']) . "</p>
                    <p><strong>Subject:</strong> " . htmlspecialchars($data['subject']) . "</p>
                </div>
                
                <div style='background: #ffffff; padding: 20px; border-left: 4px solid #3498db; margin: 20px 0;'>
                    <h3 style='margin-top: 0; color: #2c3e50;'>Message</h3>
                    <p>" . nl2br(htmlspecialchars($data['message'])) . "</p>
                </div>
                
                <div style='background: #e8f4fd; padding: 15px; border-radius: 8px; margin: 20px 0;'>
                    <p style='margin: 0; font-size: 14px; color: #2c3e50;'>
                        <strong>Submitted:</strong> " . date('F j, Y g:i A') . "<br>
                        <strong>IP Address:</strong> " . ($_SERVER['REMOTE_ADDR'] ?? 'Unknown') . "<br>
                        <strong>User Agent:</strong> " . htmlspecialchars($_SERVER['HTTP_USER_AGENT'] ?? 'Unknown') . "
                    </p>
                </div>
                
                <hr style='border: none; border-top: 1px solid #ddd; margin: 30px 0;'>
                <p style='font-size: 12px; color: #666; text-align: center;'>
                    This email was sent from the DAW Transport Company contact form.
                </p>
            </div>
        </body>
        </html>";
        
        return [
            'subject' => $subject,
            'body' => $body,
            'alt_body' => strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $body))
        ];
    }
    
    /**
     * Get auto-reply email template
     * @param array $data Form data
     * @return array Email content
     */
    public static function getAutoReplyTemplate($data) {
        $subject = "Thank you for contacting DAW Transport Company";
        
        $body = "
        <html>
        <body style='font-family: Arial, sans-serif; line-height: 1.6; color: #333;'>
            <div style='max-width: 600px; margin: 0 auto; padding: 20px;'>
                <div style='text-align: center; margin-bottom: 30px;'>
                    <h1 style='color: #3498db; margin: 0;'>🚛 DAW Transport Company</h1>
                    <p style='color: #666; margin: 5px 0;'>Professional Transport Solutions</p>
                </div>
                
                <h2 style='color: #2c3e50;'>Hello " . htmlspecialchars($data['name']) . ",</h2>
                
                <p>Thank you for reaching out to DAW Transport Company! We have received your inquiry and appreciate your interest in our services.</p>
                
                <div style='background: #f8f9fa; padding: 20px; border-radius: 8px; margin: 20px 0;'>
                    <h3 style='margin-top: 0; color: #2c3e50;'>Your Inquiry Details</h3>
                    <p><strong>Subject:</strong> " . htmlspecialchars($data['subject']) . "</p>
                    <p><strong>Service Interest:</strong> " . htmlspecialchars($data['service_type']) . "</p>
                    <p><strong>Submitted:</strong> " . date('F j, Y g:i A') . "</p>
                </div>
                
                <div style='background: #e8f4fd; padding: 20px; border-radius: 8px; margin: 20px 0;'>
                    <h3 style='margin-top: 0; color: #2c3e50;'>What Happens Next?</h3>
                    <ul style='margin: 0; padding-left: 20px;'>
                        <li>Our team will review your inquiry within 24 hours</li>
                        <li>We'll contact you via email or phone to discuss your needs</li>
                        <li>For urgent matters, please call us at <strong>(555) 123-4567</strong></li>
                    </ul>
                </div>
                
                <div style='background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px; border-radius: 8px; margin: 20px 0; text-align: center;'>
                    <h3 style='margin-top: 0; color: white;'>Our Transport Services</h3>
                    <div style='display: flex; justify-content: space-around; flex-wrap: wrap;'>
                        <div style='margin: 10px;'>🚛<br><strong>Land Transport</strong></div>
                        <div style='margin: 10px;'>✈️<br><strong>Air Transport</strong></div>
                        <div style='margin: 10px;'>🚢<br><strong>Ocean Transport</strong></div>
                    </div>
                </div>
                
                <hr style='border: none; border-top: 1px solid #ddd; margin: 30px 0;'>
                
                <div style='text-align: center;'>
                    <p><strong>DAW Transport Company</strong></p>
                    <p>
                        📧 Email: support@daw-transport.com<br>
                        📞 Phone: (555) 123-4567<br>
                        🌐 Website: www.daw-transport.com
                    </p>
                </div>
                
                <p style='font-size: 12px; color: #666; text-align: center; margin-top: 30px;'>
                    This is an automated response. Please do not reply to this email.
                </p>
            </div>
        </body>
        </html>";
        
        return [
            'subject' => $subject,
            'body' => $body,
            'alt_body' => strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $body))
        ];
    }
}
?>
