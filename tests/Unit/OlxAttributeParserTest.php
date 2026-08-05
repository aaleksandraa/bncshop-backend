<?php

namespace Tests\Unit;

use App\Services\Olx\OlxAttributeParser;
use Tests\TestCase;

class OlxAttributeParserTest extends TestCase
{
    public function test_parses_ram_ssd_and_os_from_product_title(): void
    {
        $parser = new OlxAttributeParser;
        $parsed = $parser->parseFromProductText('HP 17 i5 / 16GB / 512GB SSD / Win 11');

        $this->assertSame('16 GB', $parsed['ram']);
        $this->assertSame('512', $parsed['ssd_gb']);
        $this->assertSame('Win 11', $parsed['os']);
    }

    public function test_parses_wireless_mouse_connection(): void
    {
        $parser = new OlxAttributeParser;

        $this->assertSame(
            'Wireless (bežični)',
            $parser->parseConnection('CANYON mouse OnClick 24 BT/ Wireless Transparent/White'),
        );
    }

    public function test_parses_tv_technology_and_resolution(): void
    {
        $parser = new OlxAttributeParser;
        $parsed = $parser->parseFromProductText('Samsung 55" QLED 4K Smart TV');

        $this->assertSame('QLED', $parsed['tv_technology']);
        $this->assertSame('4K', $parsed['resolution']);
        $this->assertSame('55', $parsed['display_inch']);
    }

    public function test_parses_display_inch_only_from_explicit_markers(): void
    {
        $parser = new OlxAttributeParser;

        $this->assertSame('55', $parser->parseDisplayInch('TCL 55" 4K UHD Google TV'));
        $this->assertSame('15.6', $parser->parseDisplayInch('ASUS VivoBook 15.6 inch FHD'));
        $this->assertNull($parser->parseDisplayInch('Dahua ADS LCD panel LS550UCM-EF'));
        $this->assertNull($parser->parseDisplayInch('TCL 55P6L 4K UHD Google TV'));
    }

    public function test_does_not_infer_os_from_product_title_without_version(): void
    {
        $parser = new OlxAttributeParser;

        $this->assertSame('Nema', $parser->parseOs('HP 290 G9 i3/8G/512G/DOS'));
        $this->assertSame('Nema', $parser->parseOs('Laptop bez OS, 16GB RAM'));
        $this->assertSame('Nema', $parser->parseOs('Desktop FreeDOS 16GB RAM'));
    }

    public function test_parses_structured_diagonal_from_description(): void
    {
        $parser = new OlxAttributeParser;

        $this->assertSame('24', $parser->parseDisplayInch("Dijagonala (inch): 24\nVrsta: IPS"));
        $this->assertSame('27.5', $parser->parseDisplayInch('Veličina (inch): 27,5'));
    }

    public function test_maps_boolean_to_da_ne(): void
    {
        $parser = new OlxAttributeParser;

        $this->assertSame('Da', $parser->booleanToOlx(true));
        $this->assertSame('Ne', $parser->booleanToOlx(false));
    }
}
