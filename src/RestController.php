<?php
// Tutorial: https://ourcodeworld.com/articles/read/342/how-to-create-with-github-your-first-psr-4-composer-packagist-package-and-publish-it-in-packagist

// If you don't to add a custom vendor folder, then use the simple class
// namespace HelloComposer;
namespace kelvinwongg\CIRestController;

class RestController
{
    public function say($toSay = "Nothing given")
    {
        return $toSay;
    }
}