<?php

// Shipped code only: PHP 7.2.5 syntax floor, so trailing commas are limited to arrays
// and multiline argument lists are left alone (recent fixers would add PHP 7.3+ commas).
$config = new class() extends PrestaShop\CodingStandards\CsFixer\Config {
    public function getRules(): array
    {
        return array_merge(parent::getRules(), [
            'trailing_comma_in_multiline' => ['elements' => ['arrays']],
            'method_argument_space' => ['on_multiline' => 'ignore'],
            // The Addons validator wants the license docblock directly after the opening tag.
            'blank_line_after_opening_tag' => false,
        ]);
    }
};

/** @var \Symfony\Component\Finder\Finder $finder */
$finder = $config->setUsingCache(true)->getFinder();
$finder->in(__DIR__)->exclude('vendor')->files()->name('*.php')->path(['#^proofage\.php$#', '#^src/#', '#^controllers/#']);

return $config;
