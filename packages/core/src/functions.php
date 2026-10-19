<?php

declare(strict_types=1);

namespace Papercut;

use Papercut\Value\Length;
use Papercut\Value\Unit;

function pt(float $value): Length { return Length::of($value, Unit::Pt); }
function px(float $value): Length { return Length::of($value, Unit::Px); }
function mm(float $value): Length { return Length::of($value, Unit::Mm); }
function cm(float $value): Length { return Length::of($value, Unit::Cm); }
function in(float $value): Length { return Length::of($value, Unit::In); }
