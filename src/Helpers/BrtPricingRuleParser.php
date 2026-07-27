<?php

/**
 * @author    Massimiliano Palermo <maxx.palermo@gmail.com>
 * @copyright Since 2016 Massimiliano Palermo
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License version 3.0
 *
 * Dynamic Pricing Condition Code Rule Parser for BRT Shipments.
 */

namespace MpSoft\MpBrtRestApiShipments\Helpers;

class BrtPricingRuleParser
{
    /**
     * Evaluate rules list against shipment request data and return matching pricingConditionCode.
     *
     * @param array $requestData Shipment parameters array
     * @param string|array|null $rulesInput Rules list (JSON string, array, or text rules)
     * @param string $defaultCode Fallback code if no rule matches (default '020')
     * @return string
     */
    public static function evaluate(array $requestData, $rulesInput = null, string $defaultCode = '020'): string
    {
        if ($rulesInput === null || $rulesInput === '') {
            $rulesInput = \Configuration::get(BrtConfig::PRICING_RULES);
        }

        $rules = self::parseRulesInput($rulesInput);
        if (empty($rules)) {
            $rules = self::getDefaultRules();
        }

        if (empty($defaultCode) || $defaultCode === '020') {
            $configuredDefault = \Configuration::get(BrtConfig::PRICING_DEFAULT_CODE);
            if ($configuredDefault !== false && $configuredDefault !== '') {
                $defaultCode = (string) $configuredDefault;
            }
        }

        foreach ($rules as $rule) {
            if (self::evaluateRule($rule, $requestData)) {
                $code = (string) ($rule['pricingConditionCode'] ?? '');
                if (strcasecmp(trim($code), 'VUOTO') === 0) {
                    return '';
                }
                return $code;
            }
        }

        if (strcasecmp(trim($defaultCode), 'VUOTO') === 0) {
            return '';
        }

        return $defaultCode;
    }

    /**
     * Evaluate a single rule structure against request data.
     *
     * @param array $rule
     * @param array $requestData
     * @return bool
     */
    public static function evaluateRule(array $rule, array $requestData): bool
    {
        $conditions = $rule['conditions'] ?? [];
        if (empty($conditions) && isset($rule['expression'])) {
            $parsed = self::parseTextRule($rule['expression']);
            if ($parsed) {
                $conditions = $parsed['conditions'];
            }
        }

        if (empty($conditions)) {
            return false;
        }

        foreach ($conditions as $cond) {
            $field = trim((string) ($cond['field'] ?? ''));
            $operator = strtoupper(trim((string) ($cond['operator'] ?? '=')));
            $expectedValue = trim((string) ($cond['value'] ?? ''));

            if ($field === '') {
                continue;
            }

            $actualValue = $requestData[$field] ?? '';
            if (!self::compare($actualValue, $operator, $expectedValue)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Compare actual field value against expected value with given operator.
     *
     * @param mixed $actual
     * @param string $operator
     * @param string $expected
     * @return bool
     */
    public static function compare($actual, string $operator, string $expected): bool
    {
        $actualStr = trim((string) $actual);
        $expectedStr = trim($expected);

        if ($operator === 'RANGE') {
            $parts = explode(',', $expectedStr);
            if (count($parts) >= 2) {
                $min = (float) trim($parts[0]);
                $max = (float) trim($parts[1]);
                $act = (float) $actualStr;
                return $act >= $min && $act <= $max;
            }
        }

        if ($operator === 'IN') {
            $allowed = array_map('trim', explode(',', $expectedStr));
            return in_array($actualStr, $allowed, true);
        }

        $isNumeric = is_numeric($actualStr) && is_numeric($expectedStr);

        if ($isNumeric) {
            $act = (float) $actualStr;
            $exp = (float) $expectedStr;

            switch ($operator) {
                case '=':
                case 'EQ':
                case '==':
                    return abs($act - $exp) < 0.00001;
                case '!=':
                case 'NEQ':
                case '<>':
                    return abs($act - $exp) >= 0.00001;
                case '>':
                case 'GT':
                    return $act > $exp;
                case '>=':
                case 'GTE':
                    return $act >= $exp;
                case '<':
                case 'LT':
                    return $act < $exp;
                case '<=':
                case 'LTE':
                    return $act <= $exp;
            }
        }

        switch ($operator) {
            case '=':
            case 'EQ':
            case '==':
                return strcasecmp($actualStr, $expectedStr) === 0;
            case '!=':
            case 'NEQ':
            case '<>':
                return strcasecmp($actualStr, $expectedStr) !== 0;
            case '>':
                return strcmp($actualStr, $expectedStr) > 0;
            case '>=':
                return strcmp($actualStr, $expectedStr) >= 0;
            case '<':
                return strcmp($actualStr, $expectedStr) < 0;
            case '<=':
                return strcmp($actualStr, $expectedStr) <= 0;
        }

        return false;
    }

    /**
     * Parse raw rules input (JSON string, array, or text) into array of rules.
     *
     * @param mixed $rulesInput
     * @return array
     */
    public static function parseRulesInput($rulesInput): array
    {
        if (empty($rulesInput)) {
            return [];
        }

        if (is_array($rulesInput)) {
            $parsedRules = [];
            foreach ($rulesInput as $r) {
                if (is_array($r)) {
                    $expr = trim((string) ($r['expression'] ?? ''));
                    $code = trim((string) ($r['pricingConditionCode'] ?? ''));

                    if (!empty($expr)) {
                        $p = self::parseTextRule($expr, $code);
                        if ($p) {
                            $parsedRules[] = $p;
                            continue;
                        }
                    }
                    $parsedRules[] = $r;
                }
            }
            return $parsedRules;
        }

        if (is_string($rulesInput)) {
            $decoded = json_decode($rulesInput, true);
            if (is_array($decoded)) {
                return self::parseRulesInput($decoded);
            }

            $lines = explode("\n", $rulesInput);
            $parsedRules = [];
            foreach ($lines as $line) {
                $line = trim($line);
                if (empty($line) || strpos($line, '#') === 0) {
                    continue;
                }
                $parsed = self::parseTextRule($line);
                if ($parsed) {
                    $parsedRules[] = $parsed;
                }
            }
            return $parsedRules;
        }

        return [];
    }

    /**
     * Parse a condition text rule like:
     * {network}="D" AND {numberOfParcels} RANGE "2,5"
     *
     * @param string $text
     * @param string $codeVal
     * @return array|null
     */
    public static function parseTextRule(string $text, string $codeVal = ''): ?array
    {
        $text = trim($text);
        if (empty($text)) {
            return null;
        }

        $condParts = explode(' AND ', $text);
        $conditions = [];

        foreach ($condParts as $cp) {
            $cp = trim($cp);
            if (preg_match('/\{?([a-zA-Z0-9_]+)\}?\s*(=|!=|>|>=|<|<=|RANGE|IN)\s*["\']?([^"\']*)["\']?/i', $cp, $cm)) {
                $conditions[] = [
                    'field' => $cm[1],
                    'operator' => strtoupper($cm[2]),
                    'value' => trim($cm[3]),
                ];
            }
        }

        if (!empty($conditions)) {
            return [
                'expression' => $text,
                'conditions' => $conditions,
                'pricingConditionCode' => $codeVal,
            ];
        }

        return null;
    }

    /**
     * Validate an expression string and return error info if syntax is invalid.
     *
     * @param string $text
     * @return array|null
     */
    public static function validateRuleExpression(string $text): ?array
    {
        $text = trim($text);
        if (empty($text)) {
            return null;
        }

        $available = array_keys(self::getAvailableFields());
        $cleanFields = array_map(function ($f) {
            return strtolower(trim($f, '{}'));
        }, $available);

        $validOperators = ['=', '!=', '>', '>=', '<', '<=', 'RANGE', 'IN', '==', 'EQ', 'NEQ', 'GT', 'GTE', 'LT', 'LTE'];

        $openCount = substr_count($text, '{');
        $closeCount = substr_count($text, '}');
        if ($openCount !== $closeCount) {
            return [
                'error' => "Parentesi graffe sbilanciate: trovate {$openCount} '{' e {$closeCount} '}'.",
                'suggestion' => "Assicurati che ogni campo sia racchiuso tra parentesi graffe, es: <code>{numberOfParcels}</code>.",
            ];
        }

        if (preg_match_all('/\{([^}]+)\}/', $text, $matches)) {
            foreach ($matches[1] as $idx => $fieldName) {
                $fieldTag = $matches[0][$idx];
                $lowerName = strtolower(trim($fieldName));

                if (!in_array($lowerName, $cleanFields, true)) {
                    $closest = self::findClosestField($fieldName, $cleanFields);
                    return [
                        'error' => "Il campo <code>{$fieldTag}</code> non è un parametro riconosciuto da BRT.",
                        'suggestion' => $closest
                            ? "Forse intendevi scrivere <code>{{$closest}}</code>?<br>Campi validi: <code>" . implode('</code>, <code>', $available) . "</code>."
                            : "Campi disponibili: <code>" . implode('</code>, <code>', $available) . "</code>.",
                        'autoFix' => $closest ? ['target' => $fieldTag, 'replacement' => "{" . $closest . "}"] : null,
                    ];
                }
            }
        } else {
            return [
                'error' => "Nessun campo della spedizione racchiuso in parentesi graffe {campo} trovato nell'espressione.",
                'suggestion' => "Usa un campo valido racchiuso tra parentesi graffe, come <code>{network}</code> o <code>{numberOfParcels}</code>.",
            ];
        }

        $condParts = explode(' AND ', $text);
        foreach ($condParts as $cp) {
            $cp = trim($cp);
            if (empty($cp)) {
                continue;
            }

            if (!preg_match('/\{?([a-zA-Z0-9_]+)\}?\s*([^\s"]+)\s*["\']?([^"\']*)["\']?/', $cp, $m)) {
                return [
                    'error' => "La condizione <code>\"{$cp}\"</code> ha una struttura non valida.",
                    'suggestion' => "Il formato corretto per ciascuna condizione è <code>{campo} OPERATORE \"valore\"</code> (es: <code>{numberOfParcels}=\"1\"</code> o <code>{numberOfParcels} RANGE \"2,5\"</code>).",
                ];
            }

            $op = strtoupper(trim($m[2]));
            $val = trim($m[3] ?? '');

            if (!in_array($op, $validOperators, true)) {
                return [
                    'error' => "L'operatore <code>\"{$op}\"</code> nella condizione <code>\"{$cp}\"</code> non è supportato.",
                    'suggestion' => "Operatori validi: <code>=</code>, <code>!=</code>, <code>></code>, <code>>=</code>, <code><</code>, <code><=</code>, <code>RANGE</code>, <code>IN</code>.",
                ];
            }

            if ($op === 'RANGE') {
                if (strpos($val, 'AND') !== false || strpos($val, '-') !== false) {
                    $cleanVal = preg_replace('/\s+/', '', str_replace(['AND', '-'], ',', $val));
                    return [
                        'error' => "L'operatore RANGE richiede la sintassi con virgola <code>\"min,max\"</code> (trovato: <code>\"{$val}\"</code>).",
                        'suggestion' => "Modifica il valore in <code>RANGE \"{$cleanVal}\"</code> senza usare AND o trattini.",
                        'autoFix' => ['target' => "RANGE \"{$val}\"", 'replacement' => "RANGE \"{$cleanVal}\""],
                    ];
                }
            }
        }

        return null;
    }

    /**
     * Find closest matching field name using Levenshtein distance.
     */
    protected static function findClosestField(string $target, array $candidates): ?string
    {
        $target = strtolower(trim($target));
        $minDist = 999;
        $best = null;

        foreach ($candidates as $cand) {
            $dist = levenshtein($target, strtolower($cand));
            if ($dist < $minDist && $dist <= 4) {
                $minDist = $dist;
                $best = $cand;
            }
        }

        return $best;
    }

    /**
     * Default rules matching legacy/standard BRT logic out-of-the-box.
     *
     * @return array
     */
    public static function getDefaultRules(): array
    {
        return [
            [
                'expression' => '{network}="D" AND {numberOfParcels}="1"',
                'conditions' => [
                    ['field' => 'network', 'operator' => '=', 'value' => 'D'],
                    ['field' => 'numberOfParcels', 'operator' => '=', 'value' => '1'],
                ],
                'pricingConditionCode' => '390',
            ],
            [
                'expression' => '{network}="D" AND {numberOfParcels} RANGE "2,5"',
                'conditions' => [
                    ['field' => 'network', 'operator' => '=', 'value' => 'D'],
                    ['field' => 'numberOfParcels', 'operator' => 'RANGE', 'value' => '2,5'],
                ],
                'pricingConditionCode' => '395',
            ],
            [
                'expression' => '{network}="D" AND {numberOfParcels}>"5"',
                'conditions' => [
                    ['field' => 'network', 'operator' => '=', 'value' => 'D'],
                    ['field' => 'numberOfParcels', 'operator' => '>', 'value' => '5'],
                ],
                'pricingConditionCode' => 'VUOTO',
            ],
            [
                'expression' => '{network}="" AND {weightKG}="1" AND {volumeM3}="0.001"',
                'conditions' => [
                    ['field' => 'network', 'operator' => '=', 'value' => ''],
                    ['field' => 'weightKG', 'operator' => '=', 'value' => '1'],
                    ['field' => 'volumeM3', 'operator' => '=', 'value' => '0.001'],
                ],
                'pricingConditionCode' => '100',
            ],
        ];
    }

    /**
     * List of available field placeholders for UI tags.
     *
     * @return array
     */
    public static function getAvailableFields(): array
    {
        return [
            '{network}' => 'Network BRT (es. D, E, S, vuoto per Standard)',
            '{numberOfParcels}' => 'Numero totale di colli (es. 1, 2, 5)',
            '{weightKG}' => 'Peso totale in KG (es. 1.0, 5.25)',
            '{volumeM3}' => 'Volume totale in M³ (es. 0.001, 0.024)',
            '{deliveryFreightTypeCode}' => 'Porto (DAP = Franco, EXW = Assegnato)',
            '{serviceType}' => 'Tipo Servizio BRT (E = Express, H = 10:30, vuoto = Standard)',
            '{consigneeCountryAbbreviationISOAlpha2}' => 'Codice Nazione Destinatario ISO (es. IT, DE, FR)',
            '{consigneeProvinceAbbreviation}' => 'Provincia Destinatario (es. BO, RM, MI)',
            '{cashOnDelivery}' => 'Importo Contrassegno in € (es. 0.00, 150.00)',
            '{senderCustomerCode}' => 'Codice Cliente BRT (es. 1020101)',
            '{departureDepot}' => 'Deposito di Partenza BRT (es. 102)',
        ];
    }
}
