<?php

namespace App\Utils;

/**
 * Minimal NBT (Named Binary Tag) reader for Hypixel SkyBlock inventory data.
 * Reads Java Edition NBT binary format from gzipped base64 strings.
 *
 * NBT Spec: https://wiki.vg/NBT
 */
class NbtParser
{
    private string $data;
    private int $offset = 0;
    private int $length;

    const TAG_END        = 0;
    const TAG_BYTE       = 1;
    const TAG_SHORT      = 2;
    const TAG_INT        = 3;
    const TAG_LONG       = 4;
    const TAG_FLOAT      = 5;
    const TAG_DOUBLE     = 6;
    const TAG_BYTE_ARRAY = 7;
    const TAG_STRING     = 8;
    const TAG_LIST       = 9;
    const TAG_COMPOUND   = 10;
    const TAG_INT_ARRAY  = 11;
    const TAG_LONG_ARRAY = 12;

    /**
     * Parse base64-encoded gzipped NBT data (Hypixel inventory format).
     */
    public static function parseBase64Gzip(string $base64): ?array
    {
        $raw = base64_decode($base64, true);
        if ($raw === false) {
            return null;
        }

        $decompressed = @gzdecode($raw);
        if ($decompressed === false) {
            $decompressed = @gzuncompress($raw);
        }
        if ($decompressed === false) {
            return null;
        }

        try {
            $parser = new self($decompressed);
            return $parser->parse();
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function __construct(string $data)
    {
        $this->data   = $data;
        $this->length = strlen($data);
    }

    private function parse(): ?array
    {
        if ($this->length === 0) {
            return null;
        }

        $type = $this->readByte();
        if ($type !== self::TAG_COMPOUND) {
            return null;
        }

        // Root compound name (usually empty)
        $this->readString();

        return $this->readCompound();
    }

    // ─── Primitive readers ───────────────────────────────────────────

    private function readByte(): int
    {
        if ($this->offset >= $this->length) {
            throw new \RuntimeException('NBT: unexpected end of data (byte)');
        }
        $val = ord($this->data[$this->offset]);
        $this->offset++;
        return $val;
    }

    private function readSignedByte(): int
    {
        if ($this->offset >= $this->length) {
            throw new \RuntimeException('NBT: unexpected end of data (signed byte)');
        }
        $val = unpack('c', $this->data, $this->offset)[1];
        $this->offset++;
        return $val;
    }

    private function readShort(): int
    {
        if ($this->offset + 2 > $this->length) {
            throw new \RuntimeException('NBT: unexpected end of data (short)');
        }
        $val = unpack('n', $this->data, $this->offset)[1];
        $this->offset += 2;
        // Convert unsigned → signed 16-bit
        if ($val >= 0x8000) {
            $val -= 0x10000;
        }
        return $val;
    }

    private function readUnsignedShort(): int
    {
        if ($this->offset + 2 > $this->length) {
            throw new \RuntimeException('NBT: unexpected end of data (ushort)');
        }
        $val = unpack('n', $this->data, $this->offset)[1];
        $this->offset += 2;
        return $val;
    }

    private function readInt(): int
    {
        if ($this->offset + 4 > $this->length) {
            throw new \RuntimeException('NBT: unexpected end of data (int)');
        }
        $val = unpack('N', $this->data, $this->offset)[1];
        $this->offset += 4;
        // Convert unsigned → signed 32-bit
        if ($val >= 0x80000000) {
            $val -= 0x100000000;
        }
        return (int) $val;
    }

    private function readLong(): int|float
    {
        if ($this->offset + 8 > $this->length) {
            throw new \RuntimeException('NBT: unexpected end of data (long)');
        }
        $val = unpack('J', $this->data, $this->offset)[1];
        $this->offset += 8;
        return $val;
    }

    private function readFloat(): float
    {
        if ($this->offset + 4 > $this->length) {
            throw new \RuntimeException('NBT: unexpected end of data (float)');
        }
        $val = unpack('G', $this->data, $this->offset)[1];
        $this->offset += 4;
        return $val;
    }

    private function readDouble(): float
    {
        if ($this->offset + 8 > $this->length) {
            throw new \RuntimeException('NBT: unexpected end of data (double)');
        }
        $val = unpack('E', $this->data, $this->offset)[1];
        $this->offset += 8;
        return $val;
    }

    private function readString(): string
    {
        $len = $this->readUnsignedShort();
        if ($len === 0) {
            return '';
        }
        if ($this->offset + $len > $this->length) {
            throw new \RuntimeException('NBT: unexpected end of data (string)');
        }
        $str = substr($this->data, $this->offset, $len);
        $this->offset += $len;

        // Sanitize: Java Modified UTF-8 → valid PHP UTF-8
        if (!mb_check_encoding($str, 'UTF-8')) {
            $str = mb_convert_encoding($str, 'UTF-8', 'UTF-8');
            // Strip any remaining invalid bytes
            $str = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $str) ?? $str;
        }

        return $str;
    }

    // ─── Compound / List readers ─────────────────────────────────────

    private function readCompound(): array
    {
        $result = [];
        while ($this->offset < $this->length) {
            $type = $this->readByte();
            if ($type === self::TAG_END) {
                break;
            }
            $name = $this->readString();
            $result[$name] = $this->readTag($type);
        }
        return $result;
    }

    private function readList(): array
    {
        $tagType = $this->readByte();
        $count   = $this->readInt();
        $result  = [];

        if ($count <= 0) {
            return [];
        }

        for ($i = 0; $i < $count; $i++) {
            $result[] = $this->readTag($tagType);
        }

        return $result;
    }

    private function readTag(int $type): mixed
    {
        return match ($type) {
            self::TAG_BYTE       => $this->readSignedByte(),
            self::TAG_SHORT      => $this->readShort(),
            self::TAG_INT        => $this->readInt(),
            self::TAG_LONG       => $this->readLong(),
            self::TAG_FLOAT      => $this->readFloat(),
            self::TAG_DOUBLE     => $this->readDouble(),
            self::TAG_BYTE_ARRAY => $this->readByteArray(),
            self::TAG_STRING     => $this->readString(),
            self::TAG_LIST       => $this->readList(),
            self::TAG_COMPOUND   => $this->readCompound(),
            self::TAG_INT_ARRAY  => $this->readIntArray(),
            self::TAG_LONG_ARRAY => $this->readLongArray(),
            default              => null,
        };
    }

    // ─── Array readers ───────────────────────────────────────────────

    private function readByteArray(): array
    {
        $count = $this->readInt();
        if ($count <= 0) {
            return [];
        }
        $result = [];
        for ($i = 0; $i < $count; $i++) {
            $result[] = $this->readSignedByte();
        }
        return $result;
    }

    private function readIntArray(): array
    {
        $count = $this->readInt();
        if ($count <= 0) {
            return [];
        }
        $result = [];
        for ($i = 0; $i < $count; $i++) {
            $result[] = $this->readInt();
        }
        return $result;
    }

    private function readLongArray(): array
    {
        $count = $this->readInt();
        if ($count <= 0) {
            return [];
        }
        $result = [];
        for ($i = 0; $i < $count; $i++) {
            $result[] = $this->readLong();
        }
        return $result;
    }
}
