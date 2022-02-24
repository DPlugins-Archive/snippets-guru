<?php

namespace Dplugins\SnippetsGuru\Integration\CodeSnippets;

class Plugin
{
    public function __construct()
    {
        new Setting();
        new Operation();
        new ListTable();
        new ManageSnippet();
    }
}
