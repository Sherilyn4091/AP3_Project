<?php
// MedicalRecord.php

class MedicalRecord {
    private $conn;
    private $table_name = "MEDICAL_RECORD";

    private $medrec_id;
    private $diagnosis;
    private $prescription;
    private $visit_date;
    private $created_at;
    private $appt_id;

    public function __construct($db){
        $this->conn = $db;
    }

    // Setters
    public function setMedRecId($id) { $this->medrec_id = $id; }
    public function setDiagnosis($diagnosis) { $this->diagnosis = $diagnosis; }
    public function setPrescription($prescription) { $this->prescription = $prescription; }
    public function setVisitDate($date) { $this->visit_date = $date; }
    public function setCreatedAt($datetime) { $this->created_at = $datetime; }
    public function setApptId($id) { $this->appt_id = $id; }

    // Getters
    public function getMedRecId() { return $this->medrec_id; }
    public function getDiagnosis() { return $this->diagnosis; }
    public function getPrescription() { return $this->prescription; }
    public function getVisitDate() { return $this->visit_date; }
    public function getCreatedAt() { return $this->created_at; }
    public function getApptId() { return $this->appt_id; }

    // CRUD methods
    public function create(){
        $query = "INSERT INTO {$this->table_name} (MED_REC_DIAGNOSIS, MED_REC_PRESCRIPTION, MED_REC_VISIT_DATE, MED_REC_CREATED_AT, APPT_ID)
                  VALUES (:diagnosis, :prescription, :visit_date, :created_at, :appt_id)";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':diagnosis', $this->diagnosis);
        $stmt->bindParam(':prescription', $this->prescription);
        $stmt->bindParam(':visit_date', $this->visit_date);
        $stmt->bindParam(':created_at', $this->created_at);
        $stmt->bindParam(':appt_id', $this->appt_id);

        if($stmt->execute()){
            $this->medrec_id = $this->conn->lastInsertId();
            return true;
        }
        return false;
    }

    public function readAll(){
        $query = "SELECT * FROM {$this->table_name} ORDER BY MED_REC_VISIT_DATE DESC";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt;
    }

   public function readAllWithDetails($medrec_id = null, $appt_id = null) {
        $query = "
            SELECT
                MR.MED_REC_ID,
                MR.MED_REC_VISIT_DATE,
                MR.MED_REC_DIAGNOSIS,
                MR.MED_REC_PRESCRIPTION,
                MR.MED_REC_CREATED_AT,
                A.APPT_ID,
                CONCAT(P.PAT_FIRST_NAME, ' ', P.PAT_LAST_NAME) AS PATIENT_NAME,
                CONCAT(D.DOC_FIRST_NAME, ' ', D.DOC_LAST_NAME) AS DOCTOR_NAME
            FROM
                MEDICAL_RECORD MR
            JOIN
                APPOINTMENT A ON MR.APPT_ID = A.APPT_ID
            JOIN
                PATIENT P ON A.PAT_ID = P.PAT_ID
            JOIN
                DOCTOR D ON A.DOC_ID = D.DOC_ID
        ";

        $where_clauses = [];
        $bind_params = [];

        if (!empty($medrec_id)) {
            $where_clauses[] = "MR.MED_REC_ID = :medrec_id";
            $bind_params[':medrec_id'] = $medrec_id;
        }

        if (!empty($appt_id)) {
            $where_clauses[] = "MR.APPT_ID = :appt_id";
            $bind_params[':appt_id'] = $appt_id;
        }

        if (!empty($where_clauses)) {
            $query .= " WHERE " . implode(' AND ', $where_clauses);
        }
        
        $query .= " ORDER BY MR.MED_REC_VISIT_DATE DESC, MR.MED_REC_ID DESC";

        $stmt = $this->conn->prepare($query);
        
        foreach ($bind_params as $key => &$value) {
            $stmt->bindParam($key, $value);
        }

        $stmt->execute();
        return $stmt;
    }
    
    public function readOne(){
        $query = "SELECT * FROM {$this->table_name} WHERE MED_REC_ID = :medrec_id LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':medrec_id', $this->medrec_id);
        $stmt->execute();

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if($row){
            $this->diagnosis = $row['MED_REC_DIAGNOSIS'];
            $this->prescription = $row['MED_REC_PRESCRIPTION'];
            $this->visit_date = $row['MED_REC_VISIT_DATE'];
            $this->created_at = $row['MED_REC_CREATED_AT'];
            $this->appt_id = $row['APPT_ID'];
            return true;
        }
        return false;
    }

    public function update(){
        $query = "UPDATE {$this->table_name}
                  SET MED_REC_DIAGNOSIS = :diagnosis, MED_REC_PRESCRIPTION = :prescription, MED_REC_VISIT_DATE = :visit_date
                  WHERE MED_REC_ID = :medrec_id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':diagnosis', $this->diagnosis);
        $stmt->bindParam(':prescription', $this->prescription);
        $stmt->bindParam(':visit_date', $this->visit_date);
        $stmt->bindParam(':medrec_id', $this->medrec_id);

        return $stmt->execute();
    }

    public function delete(){
        $query = "DELETE FROM {$this->table_name} WHERE MED_REC_ID = :medrec_id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':medrec_id', $this->medrec_id);
        return $stmt->execute();
    }

    /**
     * Renders the medical record creation/update form using an internal template.
     * This separates the PHP class logic (Model) from the HTML structure (View).
     * @param string $style 'plain' or 'bootstrap' to select the view template.
     */
    public static function renderForm(MedicalRecord $record, $formAction, $submitName, $submitValue, $style = 'plain') {
        // 1. PREPARE & SANITIZE DATA (Controller Logic)
        $is_update = !empty($record->getMedRecId());
        $medrec_id = $is_update ? htmlspecialchars($record->getMedRecId()) : '';
        $appt_id = $is_update ? htmlspecialchars($record->getApptId()) : '';
        $visit_date = $is_update ? htmlspecialchars($record->getVisitDate()) : date('Y-m-d');
        $diagnosis = $is_update ? htmlspecialchars($record->getDiagnosis()) : '';
        $prescription = $is_update ? htmlspecialchars($record->getPrescription()) : '';
        
        // 2. RENDER VIEW (Uses Output Buffering)
        ob_start();
        
        // --- TEMPLATE CONTENT BLOCK (Ideally in an external file like 'medical_record_form_plain.php') ---
        if ($style === 'plain'):
        ?>
            <form method='POST' action='?action=<?php echo $formAction; ?>'>
            
                <?php if ($is_update): ?>
                    <input type='hidden' name='medrec_id' value='<?php echo $medrec_id; ?>'>
                    <p><strong>Record ID:</strong> <?php echo $medrec_id; ?></p>
                    <p><strong>Appointment ID:</strong> <?php echo $appt_id; ?></p>
                <?php else: ?>
                    <p>
                        <label for='appt_id'>Appointment ID (Required):</label><br>
                        <input type='text' id='appt_id' name='appt_id' required placeholder='e.g., 2025-01-0000001'>
                    </p>
                <?php endif; ?>

                <p>
                    <label for='visit_date'>Visit Date (YYYY-MM-DD):</label><br>
                    <input type='date' id='visit_date' name='visit_date' value='<?php echo $visit_date; ?>' required>
                </p>

                <p>
                    <label for='diagnosis'>Diagnosis:</label><br>
                    <textarea id='diagnosis' name='diagnosis' rows='4' cols='50' required><?php echo $diagnosis; ?></textarea>
                </p>

                <p>
                    <label for='prescription'>Prescription:</label><br>
                    <textarea id='prescription' name='prescription' rows='4' cols='50' required><?php echo $prescription; ?></textarea>
                </p>

                <p>
                    <input type='submit' name='<?php echo $submitName; ?>' value='<?php echo $submitValue; ?>'>
                    <a href="?action=view" style="margin-left: 10px;">Cancel</a>
                </p>
            </form>
        <?php 
        // Example of a Bootstrap template block (if needed in the future)
        elseif ($style === 'bootstrap'): 
        // ... (Bootstrap HTML structure goes here)
        endif; 
        // --- END TEMPLATE CONTENT BLOCK ---

        // 3. RETURN RENDERED CONTENT
        return ob_get_clean();
    }
}
?>