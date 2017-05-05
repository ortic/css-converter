<?php

namespace Ortic\CssConverter;

use aCssToken;
use CssAtCharsetToken;
use CssAtFontFaceDeclarationToken;
use CssAtFontFaceStartToken;
use CssAtKeyframesRulesetDeclarationToken;
use CssAtKeyframesRulesetEndToken;
use CssAtKeyframesRulesetStartToken;
use CssAtKeyframesStartToken;
use CssRulesetDeclarationToken;

abstract class Layouter
{
    protected $tree;
    protected $variables = [];
    protected $ruleSetList;
    protected $tokens;
    protected $extractVariables;

    function __construct(CssConverter $converter, $extractVariables = true)
    {
        $this->tree = $converter->getTree();
        $this->ruleSetList = $converter->getRuleSet();
        $this->tokens = $converter->getTokens();
        if ($extractVariables) {
            $this->variables = $this->extractVariables();
        }
        $this->extractVariables = $extractVariables;
    }

    protected function convertVariableName($variableName)
    {
        return "@{$variableName}";
    }

    protected function formatVariable($variableName, $value)
    {
        return $this->convertVariableName($variableName) . ": {$value};";
    }

    /**
     * Iterates through all Tokens and extracts the values into variables.
     */
    public function extractVariables()
    {
        $variables = [];
        $properties = ['color', 'font-family', 'background-color', 'border-color', 'border-top-color', 'border-right-color', 'border-bottom-color', 'border-left-color', 'outline-color'];
        foreach ($properties as $property) {
            $propertyName = str_replace('-', '_', $property);
            $variables[$propertyName] = [];
        }

        foreach ($this->tokens as $token) {
            if ($token instanceof \CssRulesetDeclarationToken && in_array($token->Property, $properties)) {
                $propertyName = str_replace('-', '_', $token->Property);
                if (!array_key_exists($token->Value, $variables[$propertyName])) {
                    $variables[$propertyName][$token->Value] = $propertyName . '_' . (count($variables[$propertyName]) + 1);
                }
                $token->Value = $this->convertVariableName($variables[$propertyName][$token->Value]);
            }
        }

        return $variables;
    }

    public function render()
    {
        $output = $this->getVariables();
        foreach ($this->tree as $node) {
            $output .= $this->formatToken($node) . "\n";
        }

        $output .= $this->format();

        return $output;
    }

    /**
     * Build and returns a tree for the CSS input.
     *
     * @return array
     */
    public function getTree()
    {
        $output = [];

        foreach ($this->ruleSetList->getList() as $ruleSet) {
            $selectors = $ruleSet->getSelectors();

            foreach ($ruleSet->getTokens() as $token) {
                $this->parseTreeNode($output, $selectors, $token);
            }
        }

        return $output;
    }

    /**
     * Returns a string containing all variables to be printed in the output
     *
     * @return string
     */
    protected function getVariables()
    {
        $return = '';
        if (is_array($this->variables) && !empty($this->variables)) {
            foreach ($this->variables as $properties) {
                foreach ($properties as $variable => $property) {
                    $return .= $this->formatVariable($property, $variable) . "\n";
                }
            }
            $return .= "\n";
        }
        return $return;
    }

    /**
     * Add support for direct descendants operator by aligning the spaces properly.
     * the code below supports "html >p" since we split by spaces. A selector "html > p" would cause an
     * additional tree level, we therefore normalize them with the two lines below.
     *
     * @param string $selector
     * @return string
     */
    protected function parseDirectDescendants($selector)
    {
        $selector = str_replace('> ', '>', $selector);
        $selector = str_replace('>', ' >', $selector);
        return $selector;
    }

    /**
     * Support for pseudo classes by adding a space before ":" and also "&" to let less know that there
     * shouldn't be a space when concatenating the nested selectors to a single css rule. We have to
     * ignore every colon if it's wrapped by :not(...) as we don't nest this in LESS.
     *
     * @param string $selector
     * @return string
     */
    protected function parsePseudoClasses($selector)
    {
        $nestedPseudo = false;
        $lastCharacterColon = false;
        $selectorOut = '';
        for ($i = 0; $i < strlen($selector); $i++) {
            $c = $selector{$i};

            // Don't parse anything between (..) and [..]
            $nestedPseudo = ($c === '(' || $c === '[') || $nestedPseudo;
            $nestedPseudo = !($c === ')' || $c === ']') && $nestedPseudo;

            if ($nestedPseudo === false && $c === ':' && $lastCharacterColon === false) {
                $selectorOut .= ' &';
                $lastCharacterColon = true;
            } else {
                $lastCharacterColon = false;
            }

            $selectorOut .= $c;
        }
        return $selectorOut;
    }

    /**
     * Ensures that operators like "+" are properly combined with a "&"
     *
     * @param $selector
     * @param array $characters
     * @return string
     */
    protected function parseSelectors($selector, array $characters)
    {
        $selectorOut = '';
        $selectorFound = false;
        for ($i = 0; $i < strlen($selector); $i++) {
            $c = $selector{$i};
            if ($c == ' ' && $selectorFound) {
                continue;
            } else {
                $selectorFound = false;
            }
            if (in_array($c, $characters)) {
                $selectorOut .= '&';
                $selectorFound = true;
            }
            $selectorOut .= $c;
        }

        return $selectorOut;
    }

    /**
     * Parse CSS input part into a LESS node
     * @param $output
     * @param $selectors
     * @param $token
     */
    protected function parseTreeNode(&$output, $selectors, $token)
    {
        // we don't parse comments
        if ($token instanceof \CssCommentToken) {
            return;
        }
        foreach ($token->MediaTypes as $mediaType) {
            // make sure we're aware of our media type
            if (!array_key_exists($mediaType, $output)) {
                $output[$mediaType] = array();
            }

            foreach ($selectors as $selector) {
                // add declaration token to output for each selector
                $currentNode = &$output[$mediaType];

                $selector = $this->parseDirectDescendants($selector);
                $selector = $this->parsePseudoClasses($selector);
                $selector = $this->parseSelectors($selector, ['+', '~']);

                // selectors like "html body" must be split into an array so we can
                // easily nest them
                $selectorPath = $this->splitSelector($selector);
                foreach ($selectorPath as $selectorPathItem) {
                    if (!array_key_exists($selectorPathItem, $currentNode)) {
                        $currentNode[$selectorPathItem] = array();
                    }
                    $currentNode = &$currentNode[$selectorPathItem];
                }

                $currentNode['@rules'][] = $this->formatToken($token);
            }
        }
    }

    /**
     * Splits CSS selectors into an array, but only where it makes sense to create a new nested level in LESS/SCSS.
     * We split "body div" into array(body, div), but we don't split "a[title='hello world']" and thus create
     * array([title='hello world'])
     *
     * @param string $selector
     * @return array
     */
    protected function splitSelector($selector)
    {
        $selectors = [];

        $currentSelector = '';
        $quoteFound = false;
        for ($i = 0; $i < strlen($selector); $i++) {
            $c = $selector{$i};

            if ($c === ' ' && !$quoteFound) {
                if (trim($currentSelector) != '') {
                    $selectors[] = trim($currentSelector);
                    $currentSelector = '';
                }
            }

            if ($quoteFound && in_array($c, ['"', '\''])) {
                $quoteFound = false;
            } elseif (!$quoteFound && in_array($c, ['"', '\''])) {
                $quoteFound = true;
            }

            $currentSelector .= $c;
        }
        if ($currentSelector != '') {
            if (trim($currentSelector) != '') {
                $selectors[] = trim($currentSelector);
            }
        }

        return $selectors;
    }

    /**
     * Format LESS nodes in a nicer way with indentation and proper brackets
     * @param $token
     * @param int $level
     * @return string
     */
    protected function formatToken(aCssToken $token, $level = 0)
    {
        $indentation = str_repeat("\t", $level);

        if ($token instanceof CssRulesetDeclarationToken) {
            return $indentation . $token->Property . ": " . $token->Value . ($token->IsImportant ? " !important" : "") . ($token->IsLast ? "" : ";");
        } elseif ($token instanceof CssAtKeyframesStartToken) {
            return $indentation . "@" . $token->AtRuleName . " \"" . $token->Name . "\" {";
        } elseif ($token instanceof CssAtKeyframesRulesetStartToken) {
            return $indentation . "\t" . implode(",", $token->Selectors) . " {";
        } elseif ($token instanceof CssAtKeyframesRulesetEndToken) {
            return $indentation . "\t" . "}";
        } elseif ($token instanceof CssAtKeyframesRulesetDeclarationToken) {
            return $indentation . "\t\t" . $token->Property . ": " . $token->Value . ($token->IsImportant ? " !important" : "") . ($token->IsLast ? "" : ";");
        } elseif ($token instanceof CssAtCharsetToken) {
            return $indentation . "@charset " . $token->Charset . ";";
        } elseif ($token instanceof CssAtFontFaceStartToken) {
            return "@font-face {";
        } elseif ($token instanceof CssAtFontFaceDeclarationToken) {
            return $indentation . "\t" . $token->Property . ": " . $token->Value . ($token->IsImportant ? " !important" : "") . ($token->IsLast ? "" : ";");
        } else {
            return $indentation . $token;
        }
    }

    protected function formatNode($selector, $level = 0)
    {
        $return = '';
        $indentation = str_repeat("\t", $level);
        foreach ($selector as $nodeKey => $node) {
            $return .= $indentation . "{$nodeKey} {\n";

            foreach ($node as $subNodeKey => $subNodes) {
                if ($subNodeKey === '@rules') {
                    foreach ($subNodes as $subNode) {
                        $return .= $indentation . "\t" . $subNode . "\n";
                    }
                } else {
                    $return .= $this->formatNode(array($subNodeKey => $subNodes), $level + 1);
                }
            }

            $return .= $indentation . "}\n";

        }
        return $return;
    }

    protected function format()
    {
        $return = '';

        foreach ($this->getTree() as $mediaType => $node) {
            if ($mediaType == 'all') {
                $return .= $this->formatNode($node);
            } else {
                $return .= "@media {$mediaType} {\n";
                $return .= $this->formatNode($node, 1);
                $return .= "}\n";
            }
        }

        return $return;
    }
}