<?php
/**
 * Invoice PDF Generator using FPDF
 */

require_once __DIR__ . '/../includes/FPDF/fpdf.php';

class Invoice extends FPDF {
    private $order;
    private $customer;
    private $company_info;

    public function __construct() {
        parent::__construct();
        
        // Company information
        $this->company_info = [
            'name' => 'TransportCorp',
            'address' => '123 Transport Avenue',
            'city' => 'Transport City, TC 12345',
            'phone' => '+1 (555) 123-SHIP',
            'email' => 'billing@transportcorp.com',
            'website' => 'www.transportcorp.com',
            'tax_id' => 'TAX-ID: TC-123456789'
        ];
    }

    public function generateInvoice($order, $customer) {
        $this->order = $order;
        $this->customer = $customer;
        
        $this->AddPage();
        $this->addHeader();
        $this->addCompanyInfo();
        $this->addCustomerInfo();
        $this->addOrderDetails();
        $this->addShippingDetails();
        $this->addCostBreakdown();

        
        return $this;
    }

    private function addHeader() {
        // Logo space (you can add a logo here later)
        $this->SetFont('Arial', 'B', 24);
        $this->SetTextColor(44, 62, 80);
        $this->Cell(0, 15, 'INVOICE', 0, 1, 'R');
        
        // Invoice details
        $this->SetFont('Arial', '', 10);
        $this->SetTextColor(0, 0, 0);
        $this->Cell(0, 5, 'Invoice #: ' . $this->order['order_number'], 0, 1, 'R');
        $this->Cell(0, 5, 'Date: ' . date('M j, Y', strtotime($this->order['created_at'])), 0, 1, 'R');
        $this->Cell(0, 5, 'Due Date: ' . date('M j, Y', strtotime($this->order['created_at'] . ' + 30 days')), 0, 1, 'R');
        $this->Ln(10);
    }

    private function addCompanyInfo() {
        $this->SetFont('Arial', 'B', 16);
        $this->SetTextColor(44, 62, 80);
        $this->Cell(0, 8, $this->company_info['name'], 0, 1);
        
        $this->SetFont('Arial', '', 10);
        $this->SetTextColor(100, 100, 100);
        $this->Cell(0, 5, $this->company_info['address'], 0, 1);
        $this->Cell(0, 5, $this->company_info['city'], 0, 1);
        $this->Cell(0, 5, $this->company_info['phone'] . ' | ' . $this->company_info['email'], 0, 1);
        $this->Cell(0, 5, $this->company_info['website'], 0, 1);
        $this->Ln(5);
    }

    private function addCustomerInfo() {
        // Bill To section
        $this->SetFont('Arial', 'B', 12);
        $this->SetTextColor(44, 62, 80);
        $this->Cell(0, 8, 'BILL TO:', 0, 1);
        
        $this->SetFont('Arial', '', 10);
        $this->SetTextColor(0, 0, 0);
        $this->Cell(0, 5, $this->customer['first_name'] . ' ' . $this->customer['last_name'], 0, 1);
        $this->Cell(0, 5, $this->customer['email'], 0, 1);
        
        if (!empty($this->customer['phone'])) {
            $this->Cell(0, 5, $this->customer['phone'], 0, 1);
        }
        
        if (!empty($this->customer['address'])) {
            $this->Cell(0, 5, $this->customer['address'], 0, 1);
        }
        
        $this->Ln(10);
    }

    private function addOrderDetails() {
        // Order information header
        $this->SetFont('Arial', 'B', 12);
        $this->SetTextColor(44, 62, 80);
        $this->Cell(0, 8, 'ORDER DETAILS', 0, 1);
        
        // Order details box
        $this->SetFillColor(248, 249, 250);
        $this->Rect(10, $this->GetY(), 190, 25, 'F');
        
        $this->SetFont('Arial', '', 10);
        $this->SetTextColor(0, 0, 0);
        
        $y_start = $this->GetY() + 3;
        
        // Left column
        $this->SetXY(15, $y_start);
        $this->Cell(90, 5, 'Order Number: ' . $this->order['order_number'], 0, 1);
        $this->SetX(15);
        $this->Cell(90, 5, 'Status: ' . ucfirst($this->order['status']), 0, 1);
        $this->SetX(15);
        $this->Cell(90, 5, 'Transport Type: ' . Order::getTransportLabel($this->order['transport_type']), 0, 1);
        
        // Right column
        $this->SetXY(105, $y_start);
        $this->Cell(90, 5, 'Tracking: ' . ($this->order['tracking_number'] ?? 'Pending'), 0, 1);
        $this->SetX(105);
        $this->Cell(90, 5, 'Weight: ' . $this->order['package_weight'] . ' kg', 0, 1);
        $this->SetX(105);
        $this->Cell(90, 5, 'Dimensions: ' . $this->formatDimensions(), 0, 1);
        
        $this->SetY($y_start + 25);
        $this->Ln(5);
    }

    private function addShippingDetails() {
        $this->SetFont('Arial', 'B', 12);
        $this->SetTextColor(44, 62, 80);
        $this->Cell(0, 8, 'SHIPPING DETAILS', 0, 1);
        
        // Addresses section
        $this->SetFont('Arial', 'B', 10);
        $this->SetTextColor(0, 0, 0);
        
        // From and To headers
        $this->Cell(90, 6, 'FROM (Pickup Address):', 0, 0);
        $this->Cell(90, 6, 'TO (Delivery Address):', 0, 1);
        
        $this->SetFont('Arial', '', 9);
        $this->SetTextColor(60, 60, 60);
        
        // Split addresses into lines
        $pickup_lines = $this->splitAddress($this->order['pickup_address']);
        $delivery_lines = $this->splitAddress($this->order['delivery_address']);
        
        $max_lines = max(count($pickup_lines), count($delivery_lines));
        
        for ($i = 0; $i < $max_lines; $i++) {
            $pickup_line = isset($pickup_lines[$i]) ? $pickup_lines[$i] : '';
            $delivery_line = isset($delivery_lines[$i]) ? $delivery_lines[$i] : '';
            
            $this->Cell(90, 4, $pickup_line, 0, 0);
            $this->Cell(90, 4, $delivery_line, 0, 1);
        }
        
        $this->Ln(5);
        
        // Package description
        if (!empty($this->order['package_description'])) {
            $this->SetFont('Arial', 'B', 10);
            $this->SetTextColor(0, 0, 0);
            $this->Cell(0, 6, 'PACKAGE DESCRIPTION:', 0, 1);
            
            $this->SetFont('Arial', '', 9);
            $this->SetTextColor(60, 60, 60);
            $this->MultiCell(0, 4, $this->order['package_description'], 0, 'L');
            $this->Ln(3);
        }
        
        // Special instructions
        if (!empty($this->order['special_instructions'])) {
            $this->SetFont('Arial', 'B', 10);
            $this->SetTextColor(0, 0, 0);
            $this->Cell(0, 6, 'SPECIAL INSTRUCTIONS:', 0, 1);
            
            $this->SetFont('Arial', '', 9);
            $this->SetTextColor(60, 60, 60);
            $this->MultiCell(0, 4, $this->order['special_instructions'], 0, 'L');
            $this->Ln(3);
        }
    }

    private function addCostBreakdown() {
        $this->SetFont('Arial', 'B', 12);
        $this->SetTextColor(44, 62, 80);
        $this->Cell(0, 8, 'COST BREAKDOWN', 0, 1);
        
        // Cost table
        $this->SetFillColor(248, 249, 250);
        $this->SetFont('Arial', 'B', 10);
        $this->SetTextColor(0, 0, 0);
        
        // Table header
        $this->Cell(120, 8, 'Description', 1, 0, 'L', true);
        $this->Cell(70, 8, 'Amount', 1, 1, 'R', true);
        
        $this->SetFont('Arial', '', 10);
        $this->SetFillColor(255, 255, 255);
        
        // Base shipping cost
        $transport_label = Order::getTransportLabel($this->order['transport_type']);
        $this->Cell(120, 7, $transport_label . ' Shipping', 1, 0, 'L', true);
        $this->Cell(70, 7, '$' . number_format($this->order['total_cost'], 2), 1, 1, 'R', true);
        
        // Additional services (if any)
        if (!empty($this->order['special_instructions'])) {
            $this->Cell(120, 7, 'Special Handling', 1, 0, 'L', true);
            $this->Cell(70, 7, 'Included', 1, 1, 'R', true);
        }
        
        // Insurance (mock)
        $this->Cell(120, 7, 'Basic Insurance', 1, 0, 'L', true);
        $this->Cell(70, 7, 'Included', 1, 1, 'R', true);
        
        // Subtotal
        $this->SetFont('Arial', 'B', 10);
        $this->Cell(120, 8, 'Subtotal', 1, 0, 'L', true);
        $this->Cell(70, 8, '$' . number_format($this->order['total_cost'], 2), 1, 1, 'R', true);
        
        // Tax (mock - you can calculate real tax)
        $tax_amount = $this->order['total_cost'] * 0.08; // 8% tax
        $this->SetFont('Arial', '', 10);
        $this->Cell(120, 7, 'Tax (8%)', 1, 0, 'L', true);
        $this->Cell(70, 7, '$' . number_format($tax_amount, 2), 1, 1, 'R', true);
        
        // Total
        $total_with_tax = $this->order['total_cost'] + $tax_amount;
        $this->SetFont('Arial', 'B', 12);
        $this->SetFillColor(44, 62, 80);
        $this->SetTextColor(255, 255, 255);
        $this->Cell(120, 10, 'TOTAL', 1, 0, 'L', true);
        $this->Cell(70, 10, '$' . number_format($total_with_tax, 2), 1, 1, 'R', true);
        
        $this->Ln(10);
    }

    public function Footer() {
        $this->SetY(-30);
        $this->SetFont('Arial', '', 8);
        $this->SetTextColor(100, 100, 100);
        
        // Terms and conditions
        $this->Cell(0, 4, 'Terms: Payment due within 30 days. Late payments may incur additional charges.', 0, 1, 'C');
        $this->Cell(0, 4, 'For questions about this invoice, please contact: ' . $this->company_info['email'], 0, 1, 'C');
        $this->Cell(0, 4, $this->company_info['tax_id'], 0, 1, 'C');
        
        // Page number
        $this->SetY(-15);
        $this->SetFont('Arial', 'I', 8);
        $this->Cell(0, 10, 'Page ' . $this->PageNo(), 0, 0, 'C');
    }

    private function formatDimensions() {
        $length = $this->order['package_length'] ?? 0;
        $width = $this->order['package_width'] ?? 0;
        $height = $this->order['package_height'] ?? 0;
        
        if ($length || $width || $height) {
            return $length . ' x ' . $width . ' x ' . $height . ' cm';
        }
        
        return 'Not specified';
    }

    private function splitAddress($address) {
        // Split address into manageable lines (max 40 chars per line)
        $lines = [];
        $words = explode(' ', $address);
        $current_line = '';
        
        foreach ($words as $word) {
            if (strlen($current_line . ' ' . $word) <= 40) {
                $current_line .= ($current_line ? ' ' : '') . $word;
            } else {
                if ($current_line) {
                    $lines[] = $current_line;
                }
                $current_line = $word;
            }
        }
        
        if ($current_line) {
            $lines[] = $current_line;
        }
        
        return $lines;
    }

    public function download($filename = null) {
        if (!$filename) {
            $filename = 'invoice_' . $this->order['order_number'] . '.pdf';
        }
        
        $this->Output('D', $filename);
    }

    public function preview() {
        $this->Output('I', 'invoice_' . $this->order['order_number'] . '.pdf');
    }
}
