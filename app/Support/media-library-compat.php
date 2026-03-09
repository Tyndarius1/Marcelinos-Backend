<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Spatie Media Library Namespace Compatibility
|--------------------------------------------------------------------------
| Some servers still run namespace layout used in older medialibrary versions
| (Spatie\MediaLibrary\HasMedia\*), while this codebase imports symbols from
| newer layout (Spatie\MediaLibrary\*). This file creates missing symbols
| early during Composer autoload so models do not fatal at load time.
*/

namespace Spatie\MediaLibrary;

if (! interface_exists(HasMedia::class, false) && interface_exists(HasMedia\HasMedia::class)) {
    interface HasMedia extends HasMedia\HasMedia
    {
    }
}

if (! trait_exists(InteractsWithMedia::class, false) && trait_exists(HasMedia\InteractsWithMedia::class)) {
    trait InteractsWithMedia
    {
        use HasMedia\InteractsWithMedia;
    }
}
