<?php

declare(strict_types=1);

namespace Papercut;

use Papercut\Backend\PhpPdf\PhpPdfQrEncoder;
use Papercut\Barcode\{ErrorCorrection, QrEncoder};
use Papercut\Concerns\HasStyle;
use Papercut\Exception\InvalidValue;

/**
 * A QR code drawn as vector squares, so it stays sharp at any size and needs no image library.
 * It is square: set width (or height) to size it; the default is 25mm. The dark modules use the
 * text color, and the quiet zone around them is part of the size.
 */
final class QrCode implements Styled
{
    use HasStyle;

    /** Modules of blank margin a scanner needs around the code. */
    public const int DEFAULT_QUIET_ZONE = 4;

    /**
     * @param non-empty-list<non-empty-list<bool>> $modules
     */
    private function __construct(
        private readonly array $modules,
        private readonly ?string $value,
        private readonly ErrorCorrection $level,
        private readonly ?QrEncoder $encoder,
        private int $quietZone = self::DEFAULT_QUIET_ZONE,
    ) {
    }

    /**
     * @param QrEncoder|null $encoder null uses php-pdf's encoder, which holds up to 213 bytes at Medium
     * @throws InvalidValue when $value is empty or too long
     */
    public static function make(string $value, ErrorCorrection $level = ErrorCorrection::Medium, ?QrEncoder $encoder = null): self
    {
        $encoder ??= new PhpPdfQrEncoder();

        return new self($encoder->encode($value, $level), $value, $level, $encoder);
    }

    /**
     * A grid you encoded yourself, rows top to bottom, truthy for dark modules.
     *
     * @param array<mixed> $modules checked here, since it usually comes from another library
     * @throws InvalidValue when the grid is empty or not square
     */
    public static function fromModules(array $modules): self
    {
        $size = count($modules);
        if ($size === 0) {
            throw InvalidValue::for('QR modules', '[]')->because('the grid is empty');
        }
        if (!array_is_list($modules)) {
            throw InvalidValue::for('QR modules', 'keyed array')->because('rows must be a list, top to bottom');
        }
        foreach ($modules as $i => $row) {
            if (!is_array($row) || count($row) !== $size || !array_is_list($row)) {
                throw InvalidValue::for('QR modules', "row $i")->because("each row needs $size modules, to make a square");
            }
        }
        /** @var non-empty-list<non-empty-list<bool>> $modules */
        return new self(array_map(static fn (array $row): array => array_map(static fn (mixed $m): bool => (bool) $m, $row), $modules), null, ErrorCorrection::Medium, null);
    }

    /** Encodes the same text again at another level. */
    public function errorCorrection(ErrorCorrection $level): self
    {
        if ($this->value === null || $this->encoder === null) {
            throw new \LogicException('A QR code made from modules has no text to encode again.');
        }
        $clone = new self($this->encoder->encode($this->value, $level), $this->value, $level, $this->encoder, $this->quietZone);
        $clone->style = $this->style;
        $clone->styleName = $this->styleName;

        return $clone;
    }

    public function quietZone(int $modules): self
    {
        if ($modules < 0) {
            throw InvalidValue::for('quiet zone', (string) $modules)->because('it cannot be negative');
        }
        $clone = clone $this;
        $clone->quietZone = $modules;

        return $clone;
    }

    /** @return non-empty-list<non-empty-list<bool>> */
    public function modules(): array
    {
        return $this->modules;
    }

    public function value(): ?string
    {
        return $this->value;
    }

    public function level(): ErrorCorrection
    {
        return $this->level;
    }

    public function quietZoneModules(): int
    {
        return $this->quietZone;
    }

    /** Modules across, quiet zone included. */
    public function span(): int
    {
        return count($this->modules) + 2 * $this->quietZone;
    }

    public function debugName(): string
    {
        return 'qr-code';
    }
}
