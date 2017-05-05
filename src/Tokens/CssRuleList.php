<?php

namespace Ortic\CssConverter\Tokens;

/**
 * Class CssRuleList.
 */
class CssRuleList
{
    private $list = [];

    /**
     * Add a new rule object to our list.
     *
     * @param LessRule $rule
     */
    public function addRule(CssRule $rule)
    {
        $this->list[] = $rule;
    }

    /**
     * @return array
     */
    public function getList()
    {
        return $this->list;
    }

}
