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

    public function test_parses_ram_skipping_invalid_gb_tokens(): void
    {
        $parser = new OlxAttributeParser;

        $this->assertSame('16 GB', $parser->parseRam('Ryzen 3 308GB DDR5, 16GB'));
        $this->assertSame('8 GB', $parser->parseRam('HP 250R G10 I5/8/512 5GHz8GB DDR4, 512GB SSD'));
        $this->assertSame('16 GB', $parser->parseRam('Core5 120 1.4/5GHz16GB DDR5, 512GB SSD'));
    }

    public function test_parses_os_shortcuts_and_ubuntu(): void
    {
        $parser = new OlxAttributeParser;

        $this->assertSame('Win 11', $parser->parseOs('HP Z2 Mini U7-265/32GB Win11p'));
        $this->assertSame('Win 11', $parser->parseOs('Dell Pro /W11Pro/3Y'));
        $this->assertSame('Linux', $parser->parseOs('Dell Pro Max 16 Plus Ubuntu'));
        $this->assertSame('Mac OS', $parser->parseOs('Apple MacBook Air 13 M4'));
    }

    public function test_parses_intel_ultra_and_core_without_i(): void
    {
        $parser = new OlxAttributeParser;

        $this->assertSame('Intel', $parser->parseProcessorBrand('ThinkCentre NEO 50 Tower G6, Ultra 7 265 20C'));
        $this->assertSame('Intel', $parser->parseProcessorBrand('HP Z2 Mini G1i U7-265/32GB'));
        $this->assertSame('Intel', $parser->parseProcessorBrand('Acer Aspire Lite Core5 120'));
        $this->assertSame('Apple', $parser->parseProcessorBrand('Apple Macbook Air 13 2023 M4'));
    }

    public function test_parses_display_from_fhd_and_monitor_size(): void
    {
        $parser = new OlxAttributeParser;

        $this->assertSame('55', $parser->parseDisplayInch('TCL 55" 4K UHD Google TV'));
        $this->assertSame('15.6', $parser->parseDisplayInch('ASUS VivoBook 15.6 inch FHD'));
        $this->assertSame('15.6', $parser->parseDisplayInch('HP 15-FD0154WM 15.6 FHD Touch, i5-1334u'));
        $this->assertSame('17.3', $parser->parseDisplayInch('Acer Aspire A17-51M 17,3 FHD, Core5'));
        $this->assertSame('27', $parser->parseDisplayInch('Monitor Dell 27 SE2726H, 1920x1080, FHD'));
        $this->assertSame('65', $parser->parseDisplayInch('Philips 65MLED920/12 AMBILIGHT 4K'));
        $this->assertSame('24', $parser->parseDisplayInch('TESLA TV 24E655BHS HD Google'));
        $this->assertNull($parser->parseDisplayInch('TCL 55P6L 4K UHD Google TV'));
        $this->assertNull($parser->parseDisplayInch('Dahua ADS LCD panel LS550UCM-EF'));
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
