<?php

namespace Ortic\CssConverter;

class ScssLayouter extends Layouter
{
    protected function convertVariableName($variableName)
    {
        return "${$variableName}";
    }

}