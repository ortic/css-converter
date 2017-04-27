<?php

namespace Ortic\CssConverter\Tokens;

/**
 * Class LessRule.
 */
class CssRule
{
    private $selectors = [];
    private $tokens = [];

    /**
     * @param $selectors
     */
    public function __construct($selectors)
    {
        $this->selectors = $selectors;
    }

    /**
     * Add new node to rule.
     *
     * @param $token
     */
    public function addToken($token)
    {
        $this->tokens[] = $token;
    }

    /**
     * Returns the list of selectors (e.g. #logo img).
     *
     * @return array
     */
    public function getSelectors()
    {
        return $this->selectors;
    }

    /**
     * Returns a list of Tokens/nodes for the current selector.
     *
     * @return array
     */
    public function getTokens()
    {
        return $this->tokens;
    }
}
