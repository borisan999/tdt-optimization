<?php

class ValidationEngine {

    private $ruleModel;

    public function __construct($ruleModel)
    {
        $this->ruleModel = $ruleModel;
    }

    /**
     * Validate a single field using rules stored in DB
     */
    public function validate($field_name, $value)
    {
        $rules = $this->ruleModel->getRulesForField($field_name);

        $messages = [];

        foreach ($rules as $r) {

            $ruleType = $r['rule_type'];
            $ruleValue = $r['rule_value'];
            $severity = $r['severity'];
            $msg = $r['message'];

            switch ($ruleType) {

                case 'min':
                    if ($value < $ruleValue) {
                        $messages[] = [
                            'severity' => $severity,
                            'message'  => $msg
                        ];
                    }
                    break;

                case 'max':
                    if ($value > $ruleValue) {
                        $messages[] = [
                            'severity' => $severity,
                            'message'  => $msg
                        ];
                    }
                    break;

                case 'regex':
                    if (!preg_match($ruleValue, $value)) {
                        $messages[] = [
                            'severity' => $severity,
                            'message'  => $msg
                        ];
                    }
                    break;

                case 'not_empty':
                    if (trim($value) === '') {
                        $messages[] = [
                            'severity' => $severity,
                            'message'  => $msg
                        ];
                    }
                    break;
            }
        }

        return $messages;
    }
}
