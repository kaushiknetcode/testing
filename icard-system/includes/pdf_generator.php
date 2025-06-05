<?php
error_reporting(E_ERROR | E_PARSE); // Hide warnings, show only real errors
require_once(__DIR__ . '/../tcpdf/tcpdf/tcpdf.php');
require_once(__DIR__ . '/../config/constants.php');

class ICardGenerator extends TCPDF {
    
    // POSITIONING SETTINGS - Your exact positions preserved
    private $show_boxes = false;
    
    // FRONT SIDE POSITIONS (all measurements in mm) - YOUR EXACT POSITIONS
    private $front_positions = [
        'department' => ['x' => 50, 'y' => 15, 'width' => 40, 'height' => 4, 'font_size' => 8, 'font_weight' => 'B', 'color' => [0,0,0]],
        'icard_number' => ['x' => 22, 'y' => 19.5, 'width' => 30, 'height' => 3, 'font_size' => 7, 'font_weight' => 'B', 'color' => [255,255,255]],
        'year_of_issue' => ['x' => 78, 'y' => 19.5, 'width' => 15, 'height' => 3, 'font_size' => 7, 'font_weight' => 'B', 'color' => [255,255,255]],
        'name' => ['x' => 41.6, 'y' => 24.8, 'width' => 52, 'height' => 3, 'font_size' => 7, 'font_weight' => 'B', 'color' => [0,0,0]],
        'designation' => ['x' => 49.4, 'y' => 29.6, 'width' => 44, 'height' => 3, 'font_size' => 7, 'font_weight' => 'B', 'color' => [0,0,0]],
        'emp_number' => ['x' => 58, 'y' => 34.6, 'width' => 25, 'height' => 3, 'font_size' => 7, 'font_weight' => 'B', 'color' => [0,0,0]],
        'hrms_id' => ['x' => 58, 'y' => 39.3, 'width' => 20, 'height' => 3, 'font_size' => 7, 'font_weight' => 'B', 'color' => [0,0,0]],
        'dob' => ['x' => 47, 'y' => 44.2, 'width' => 20, 'height' => 3, 'font_size' => 7, 'font_weight' => 'B', 'color' => [0,0,0]],
        'photo' => ['x' => 6, 'y' => 26, 'width' => 16, 'height' => 20],
        'emp_signature' => ['x' => 7, 'y' => 48.5, 'width' => 15, 'height' => 6]
    ];
    
    // BACK SIDE POSITIONS - YOUR EXACT POSITIONS
    private $back_positions = [
        'appointment_date' => ['x' => 35, 'y' => 4, 'width' => 20, 'height' => 3, 'font_size' => 7, 'font_weight' => 'B', 'color' => [0,0,0]],
        'retirement_date' => ['x' => 33, 'y' => 8.8, 'width' => 20, 'height' => 3, 'font_size' => 7, 'font_weight' => 'B', 'color' => [0,0,0]],
        'joining_date' => ['x' => 62, 'y' => 13.8, 'width' => 20, 'height' => 3, 'font_size' => 7, 'font_weight' => 'B', 'color' => [0,0,0]],
        'blood_group' => ['x' => 23.5, 'y' => 18.5, 'width' => 10, 'height' => 3, 'font_size' => 7, 'font_weight' => 'B', 'color' => [0,0,0]],
        'height' => ['x' => 62, 'y' => 18.5, 'width' => 15, 'height' => 3, 'font_size' => 7, 'font_weight' => 'B', 'color' => [0,0,0]],
        'mobile_no' => ['x' => 20.4, 'y' => 23.3, 'width' => 25, 'height' => 3, 'font_size' => 7, 'font_weight' => 'B', 'color' => [0,0,0]],
        'identification_mark' => ['x' => 32, 'y' => 28, 'width' => 60, 'height' => 3, 'font_size' => 7, 'font_weight' => 'B', 'color' => [0,0,0]],
        'address' => ['x' => 33.5, 'y' => 32.7, 'width' => 60, 'height' => 6, 'font_size' => 7, 'font_weight' => 'B', 'color' => [0,0,0], 'multiline' => true]
    ];

    public function __construct() {
        parent::__construct('L', 'mm', array(95, 60)); // Landscape, ID card size in mm
        $this->SetMargins(0, 0, 0);
        $this->SetAutoPageBreak(false);
    }

    public function generateICard($data) {
        $this->generateFront($data);
        $this->generateBack($data);
    }

    private function generateFront($data) {
        $this->AddPage();
        
        // Add background image from assets folder
        $front_image_path = __DIR__ . '/../assets/images/icard_front.png';
        if (file_exists($front_image_path)) {
            $this->Image($front_image_path, 0, 0, 95, 60, '', '', '', false, 300);
        }
        
        // Add all front elements
        foreach ($this->front_positions as $field => $pos) {
            if ($field == 'photo') {
                $this->addPhoto($data['photo_path'] ?? '', $pos, 'PHOTO AREA');
            } elseif ($field == 'emp_signature') {
                $this->addSignature($data['signature_path'] ?? '', $pos, 'EMP SIGNATURE');
            } elseif ($field == 'year_of_issue') {
                $year = date('Y', strtotime($data['issue_date'] ?? date('Y-m-d')));
                $this->addTextField($year, $pos, $field);
            } else {
                $this->addTextField($data[$field] ?? '', $pos, $field);
            }
        }
        
        if ($this->show_boxes) {
            $this->addDebugInfo('FRONT SIDE');
        }
    }

    private function generateBack($data) {
        $this->AddPage();
        
        // Add background image from assets folder
        $back_image_path = __DIR__ . '/../assets/images/icard_back.png';
        if (file_exists($back_image_path)) {
            $this->Image($back_image_path, 0, 0, 95, 60, '', '', '', false, 300);
        }
        
        // Add all back elements
        foreach ($this->back_positions as $field => $pos) {
            $this->addTextField($data[$field] ?? '', $pos, $field);
        }
        
        if ($this->show_boxes) {
            $this->addDebugInfo('BACK SIDE');
        }
    }

    private function addTextField($text, $position, $field_name) {
        if ($this->show_boxes) {
            $this->SetDrawColor(255, 0, 0);
            $this->SetLineWidth(0.1);
            $this->Rect($position['x'], $position['y'], $position['width'], $position['height']);
            
            $this->SetFont('helvetica', '', 6);
            $this->SetTextColor(255, 0, 0);
            $this->SetXY($position['x'], $position['y'] - 2);
            $this->Cell($position['width'], 2, strtoupper($field_name), 0, 0, 'L');
        }
        
        $this->SetFont('helvetica', $position['font_weight'], $position['font_size']);
        $this->SetTextColor($position['color'][0], $position['color'][1], $position['color'][2]);
        
        $this->SetXY($position['x'], $position['y']);
        
        $display_text = !empty($text) ? $text : '[' . strtoupper($field_name) . ']';
        
        // Check if this field supports multiline (for address)
        if (isset($position['multiline']) && $position['multiline']) {
            // Use MultiCell for address to support multiple lines
            $text_width = $this->GetStringWidth($display_text);
            if ($text_width > $position['width']) {
                $new_font_size = $position['font_size'] * ($position['width'] / $text_width) * 0.9;
                $final_font_size = max(6, min(8, $new_font_size));
                $this->SetFont('helvetica', $position['font_weight'], $final_font_size);
            }
            
            // Use MultiCell for multi-line text with line height of 2.5mm
            $this->MultiCell($position['width'], 2.5, $display_text, 0, 'L');
        } else {
            // Regular single-line Cell for other fields
            $text_width = $this->GetStringWidth($display_text);
            if ($text_width > $position['width']) {
                $new_font_size = $position['font_size'] * ($position['width'] / $text_width) * 0.9;
                $final_font_size = max(6, min(8, $new_font_size));
                $this->SetFont('helvetica', $position['font_weight'], $final_font_size);
            }
            
            if (empty($text) && $this->show_boxes) {
                $this->SetFillColor(255, 255, 200);
                $this->Cell($position['width'], $position['height'], $display_text, 0, 0, 'L', true);
            } else {
                $this->Cell($position['width'], $position['height'], $display_text, 0, 0, 'L');
            }
        }
    }

    private function addPhoto($photo_path, $position, $label) {
        if ($this->show_boxes) {
            $this->SetDrawColor(0, 255, 0);
            $this->SetLineWidth(0.2);
            $this->Rect($position['x'], $position['y'], $position['width'], $position['height']);
            
            $this->SetFont('helvetica', 'B', 6);
            $this->SetTextColor(0, 255, 0);
            $this->SetXY($position['x'], $position['y'] - 2);
            $this->Cell($position['width'], 2, $label, 0, 0, 'C');
        }
        
        if (!empty($photo_path) && file_exists($photo_path)) {
            $this->Image($photo_path, $position['x'], $position['y'], $position['width'], $position['height'], '', '', '', true, 300);
        } else {
            if ($this->show_boxes) {
                $this->SetFillColor(230, 230, 230);
                $this->Rect($position['x'], $position['y'], $position['width'], $position['height'], 'F');
                
                $this->SetFont('helvetica', 'B', 6);
                $this->SetTextColor(100, 100, 100);
                $this->SetXY($position['x'], $position['y'] + ($position['height']/2) - 2);
                $this->Cell($position['width'], 2, 'EMPLOYEE', 0, 1, 'C');
                $this->SetXY($position['x'], $position['y'] + ($position['height']/2));
                $this->Cell($position['width'], 2, 'PHOTO', 0, 0, 'C');
            }
        }
    }

    private function addSignature($signature_path, $position, $label) {
        if ($this->show_boxes) {
            $this->SetDrawColor(0, 0, 255);
            $this->SetLineWidth(0.1);
            $this->Rect($position['x'], $position['y'], $position['width'], $position['height']);
            
            $this->SetFont('helvetica', '', 6);
            $this->SetTextColor(0, 0, 255);
            $this->SetXY($position['x'], $position['y'] - 2);
            $this->Cell($position['width'], 2, $label, 0, 0, 'C');
        }
        
        if (!empty($signature_path) && file_exists($signature_path)) {
            $this->Image($signature_path, $position['x'], $position['y'], $position['width'], $position['height'], '', '', '', true, 300);
        } else {
            if ($this->show_boxes) {
                $this->SetFillColor(245, 245, 245);
                $this->Rect($position['x'], $position['y'], $position['width'], $position['height'], 'F');
                
                $this->SetFont('helvetica', 'I', 6);
                $this->SetTextColor(150, 150, 150);
                $this->SetXY($position['x'], $position['y'] + ($position['height']/2) - 1);
                $this->Cell($position['width'], 2, 'Signature Area', 0, 0, 'C');
            }
        }
    }

    private function addDebugInfo($side) {
        $this->SetFont('helvetica', 'B', 6);
        $this->SetTextColor(255, 255, 255);
        $this->SetFillColor(0, 0, 0);
        $this->SetXY(2, 2);
        $this->Cell(40, 3, $side . ' - POSITIONING MODE', 0, 0, 'L', true);
        
        $this->SetFont('helvetica', '', 6);
        $this->SetTextColor(255, 255, 255);
        $this->SetFillColor(50, 50, 50);
        $this->SetXY(2, 56);
        $this->Cell(90, 3, 'RED=Text | GREEN=Photo | BLUE=Signature | Set $show_boxes=false when done', 0, 0, 'L', true);
    }
}

/**
 * Generate I-Card PDF - SAFE VERSION (uses existing database structure)
 */
function generateICardPDF($application_data, $employee_data, $icard_number) {
    try {
        // Prepare data for PDF generation
        $pdf_data = [
            // Front side data
            'department' => strtoupper($application_data['department_name'] ?? 'RAILWAY'),
            'icard_number' => $icard_number,
            'issue_date' => date('Y-m-d'),
            'name' => strtoupper($employee_data['name']),
            'designation' => $application_data['designation'] ?? '',
            'emp_number' => $employee_data['emp_number'],
            'hrms_id' => $employee_data['hrms_id'],
            'dob' => date('d-m-Y', strtotime($employee_data['dob'])),
            'photo_path' => !empty($application_data['photo_path']) ? __DIR__ . '/../uploads/photos/' . $application_data['photo_path'] : '',
            'signature_path' => !empty($application_data['signature_path']) ? __DIR__ . '/../uploads/signatures/' . $application_data['signature_path'] : '',
            
            // Back side data
            'appointment_date' => !empty($application_data['date_of_appointment']) ? date('d-m-Y', strtotime($application_data['date_of_appointment'])) : '',
            'retirement_date' => !empty($application_data['date_of_retirement']) ? date('d-m-Y', strtotime($application_data['date_of_retirement'])) : '',
            'joining_date' => !empty($application_data['date_of_joining']) ? date('d-m-Y', strtotime($application_data['date_of_joining'])) : '',
            'blood_group' => $application_data['blood_group'] ?? '',
            'height' => $application_data['height'] ?? '',
            'mobile_no' => $application_data['mobile_no'] ?? '',
            'identification_mark' => $application_data['identification_mark'] ?? '',
            // FIXED: Use line break instead of comma for address
            'address' => trim(($application_data['address_line1'] ?? '') . (!empty($application_data['address_line2']) ? "\n" . $application_data['address_line2'] : ''))
        ];

        // Generate PDF
        $icard = new ICardGenerator();
        $icard->generateICard($pdf_data);
        
        // Create uploads/icards directory if it doesn't exist
        $icards_dir = __DIR__ . '/../uploads/icards/';
        if (!is_dir($icards_dir)) {
            mkdir($icards_dir, 0755, true);
        }
        
        // Generate filename
        $safe_icard_number = str_replace('/', '_', $icard_number);
        $filename = $safe_icard_number . '_' . $employee_data['hrms_id'] . '.pdf';
        $filepath = $icards_dir . $filename;
        
        // Save PDF to file
        $icard->Output($filepath, 'F');
        
        return $filename;
        
    } catch (Exception $e) {
        error_log('PDF Generation Error: ' . $e->getMessage());
        return false;
    }
}

/**
 * Generate I-Card number - USING CONSTANTS & SEPARATE SEQUENCES
 * GAZ: 1,2,3,4... | NG: 1,2,3,4...
 */
function generateICardNumber($conn, $category) {
    try {
        // Use constants for prefixes
        if ($category === EMP_CATEGORY_GAZETTED) {
            $prefix = ICARD_PREFIX_GAZ;  // 'ERKPAW/GAZ'
        } else {
            $prefix = ICARD_PREFIX_NG;   // 'ERKPAW/NG'
        }
        
        // Get sequence for the specific category (separate sequences)
        $seq_sql = "SELECT sequence FROM icard_sequence WHERE prefix = ? FOR UPDATE";
        $seq_stmt = $conn->prepare($seq_sql);
        $seq_stmt->bind_param("s", $prefix);
        $seq_stmt->execute();
        $seq_result = $seq_stmt->get_result();
        
        if ($seq_result->num_rows === 0) {
            throw new Exception("Sequence not found for prefix: $prefix");
        }
        
        $seq_row = $seq_result->fetch_assoc();
        $next_sequence = $seq_row['sequence'];
        
        // Update sequence for next use (for this specific category)
        $update_seq_sql = "UPDATE icard_sequence SET sequence = sequence + 1 WHERE prefix = ?";
        $update_stmt = $conn->prepare($update_seq_sql);
        $update_stmt->bind_param("s", $prefix);
        $update_stmt->execute();
        
        // Format the final I-Card number using constants
        if ($category === EMP_CATEGORY_GAZETTED) {
            $formatted_number = ICARD_PREFIX_GAZ . str_pad($next_sequence, 4, '0', STR_PAD_LEFT);
            // Result: ERKPAW/GAZ0001, ERKPAW/GAZ0002, etc.
        } else {
            $formatted_number = ICARD_PREFIX_NG . str_pad($next_sequence, 5, '0', STR_PAD_LEFT);
            // Result: ERKPAW/NG00001, ERKPAW/NG00002, etc.
        }
        
        return $formatted_number;
        
    } catch (Exception $e) {
        error_log('I-Card Number Generation Error: ' . $e->getMessage());
        throw $e;
    }
}
?>