<?php

namespace Ortic\CssConverter;

use Ortic\CssConverter\tokens\CssRuleList;
use Ortic\CssConverter\tokens\CssRule;

class CssConverter
{
    /**
     * @var string
     */
    protected $cssContent;

    /**
     * @var \CssParser
     */
    protected $parser;

    /**
     * Tokens.
     *
     * @var array
     */
    protected $tokens = [];

    /**
     * List of CSS rules.
     *
     * @var CssRuleList
     */
    protected $ruleSetList;

    protected $tree = [];

    /**
     * Create a new parser object, use parameter to specify CSS you
     * wish to convert into a LESS or SCSS file.
     *
     * @param string $cssContent
     */
    public function __construct($cssContent)
    {
        $this->cssContent = $cssContent;
        $this->parser = new \CssParser($this->cssContent);
        $this->tokens = $this->parser->getTokens();
        $this->ruleSetList = new CssRuleList();
        $this->buildTree();
    }

    /**
     * Iterates through all Tokens and extracts the values into variables.
     */
/*    public function getVariables()
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
                $token->Value = '@' . $variables[$propertyName][$token->Value];
            }
        }

        return $variables;
    }*/

    public function getTree()
    {
        return $this->tree;
    }

    public function getRuleSet()
    {
        return $this->ruleSetList;
    }

    public function getTokens()
    {
        return $this->tokens;
    }

    public function buildTree()
    {
        // this variable is true, if we're within a ruleset, e.g. p { .. here .. }
        // we have to normalize them
        $withinRulset = false;
        $ruleSet = null;
        $this->ruleSetList = new CssRuleList();

        foreach ($this->tokens as $token) {
            // we have to skip some Tokens, their information is redundant
            if ($token instanceof \CssAtMediaStartToken || $token instanceof \CssAtMediaEndToken) {
                continue;
            }

            // we have to build a hierarchy with CssRulesetStartToken, CssRulesetEndToken
            if ($token instanceof \CssRulesetStartToken) {
                $withinRulset = true;
                $ruleSet = new CssRule($token->Selectors);
            } elseif ($token instanceof \CssRulesetEndToken) {
                $withinRulset = false;
                if ($ruleSet) {
                    $this->ruleSetList->addRule($ruleSet);
                }
                $ruleSet = null;
            } else {
                // as long as we're in a ruleset, we're adding all token to a custom array
                // this will be converted once we've found CssRulesetEndToken and then added
                // to the actual $tree variable
                if ($withinRulset) {
                    $ruleSet->addToken($token);
                } else {
                    $this->tree[] = $token;
                }
            }
        }
    }

}
