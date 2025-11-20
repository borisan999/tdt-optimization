<?php

require_once __DIR__ . "/../config/db.php";

class ValidationRules
{
    private $pdo;

    public function __construct() {
        $db = new Database();
        $this->pdo = $db->getConnection();   // correct property
    }

    public function getRulesForField($field_name)
    {
        $sql = "SELECT * FROM validation_rules WHERE field_name = :f";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':f' => $field_name]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function validate($field_name, $value)
    {
        $rules = $this->getRulesForField($field_name);
        $messages = [];

        foreach ($rules as $r) {
            $ruleType = $r['rule_type'];
            $ruleVal  = $r['rule_value'];
            $msg      = $r['message'];
            $sev      = $r['severity'];

            if ($ruleType === 'min' && is_numeric($value)) {
                if ($value < $ruleVal)
                    $messages[] = ['severity' => $sev, 'message' => $msg];
            }

            if ($ruleType === 'max' && is_numeric($value)) {
                if ($value > $ruleVal)
                    $messages[] = ['severity' => $sev, 'message' => $msg];
            }

            if ($ruleType === 'regex') {
                if (!preg_match("/{$ruleVal}/", $value))
                    $messages[] = ['severity' => $sev, 'message' => $msg];
            }

            if ($ruleType === 'not_empty') {
                if ($value === "" || $value === null)
                    $messages[] = ['severity' => $sev, 'message' => $msg];
            }
        }

        return $messages;
    }
}
