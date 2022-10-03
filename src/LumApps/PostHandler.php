<?php

namespace Comeen\LumApps\Post;

use ComeenPlay\SdkPhp\Handlers\SlideHandler;
use ComeenPlay\SdkPhp\Interfaces\ISlide;
use ComeenPlay\SdkPhp\Interfaces\IDisplay;

class PostHandler extends SlideHandler
{
    public function fetch(ISlide $slide, IDisplay $display): void
    {
        $this->addSlide([
            // 'url' => $slide->getOption('url', ""),
        ]);
    }

    public function getValidations($options = null): array
    {
        return [
            'rules' => [
                'url' => ['required']
            ],
            'messages' => [
                'url.required' => ""
            ],
        ];
    }
}
