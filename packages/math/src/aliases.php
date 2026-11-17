<?php

declare(strict_types=1);

/*
 * Deprecated since 0.17.0, removed in 1.0: Quoin\Math\Font\TrueTypeFont is the old name of Font.
 *
 * Registered through composer's "autoload.files" so the alias exists before any
 * type declaration or instanceof check names it; PHP does not autoload for those.
 */
class_alias(\Quoin\Math\Font\Font::class, 'Quoin\Math\Font\TrueTypeFont');
