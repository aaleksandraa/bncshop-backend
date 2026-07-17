<?php

/**
 * OLX attribute mapping definitions per category (rev. 3).
 * Used by OlxAttributeMappingSeeder.
 *
 * @return array<int, array<int, array<string, mixed>>>
 */
return [
    // Monitori
    163 => [
        ['olx_attribute_id' => 1143, 'attribute_definition_id' => 349, 'bnc_attribute_aliases' => ['Dijagonala ekrana', 'Dijagonala', 'Display', 'Ekran', 'Veličina ekrana', 'Veličina monitora', 'Veličina (inch)'], 'is_required_for_publish' => true],
        ['olx_attribute_id' => 369, 'bnc_attribute_aliases' => ['Vrsta panela', 'Tip panela', 'Vrsta ekrana', 'Panel'], 'default_value' => 'LED', 'is_required_for_publish' => true, 'value_mappings' => ['IPS' => 'LCD', 'VA' => 'LCD', 'TN' => 'LCD', 'LED' => 'LED', 'OLED' => 'OLED', 'QLED' => 'LED']],
        ['olx_attribute_id' => 6671, 'bnc_attribute_aliases' => ['Rezolucija', 'Maksimalna rezolucija'], 'value_mappings' => ['1920x1080' => 'Full HD', '3840x2160' => '4K', '2560x1440' => '2K']],
        ['olx_attribute_id' => 5197, 'bnc_attribute_aliases' => ['Refresh rate', 'Osvežavanje', 'Frekvencija']],
        ['olx_attribute_id' => 5160, 'bnc_attribute_aliases' => ['Garancija', 'Jamstvo']],
        ['olx_attribute_id' => 2471, 'bnc_attribute_aliases' => ['HDMI', 'Broj HDMI']],
        ['olx_attribute_id' => 3080, 'bnc_attribute_aliases' => ['HDMI']],
    ],

    // Desktop računari
    38 => [
        ['olx_attribute_id' => 245, 'bnc_attribute_aliases' => ['Procesor', 'CPU'], 'is_required_for_publish' => true],
        ['olx_attribute_id' => 1156, 'bnc_attribute_aliases' => ['Brzina procesora', 'Brzina procesora (GHz)', 'GHz'], 'default_value' => '2.5', 'is_required_for_publish' => true],
        ['olx_attribute_id' => 238, 'attribute_definition_id' => 1170, 'bnc_attribute_aliases' => ['Operativni sistem', 'Operativni Sustav', 'Operativni sistem - uređaj', 'Operativni Sustavi', 'OS'], 'is_required_for_publish' => true, 'value_mappings' => [
            'FreeDOS' => 'Nema', 'DOS' => 'Nema', 'Bez OS-a' => 'Nema', 'Bez OS' => 'Nema', 'Without OS' => 'Nema', 'No OS' => 'Nema',
            'Win 11' => 'Win 11', 'Windows 11' => 'Win 11', 'Win 10' => 'Win 10', 'Windows 10' => 'Win 10',
            'macOS' => 'Mac OS', 'Mac OS' => 'Mac OS', 'Apple Mac OS' => 'Mac OS', 'Linux' => 'Linux', 'Ostalo' => 'Nema',
        ]],
        ['olx_attribute_id' => 246, 'bnc_attribute_aliases' => ['RAM', 'Memorija RAM', 'Memorija'], 'is_required_for_publish' => true],
        ['olx_attribute_id' => 4785, 'bnc_attribute_aliases' => ['SSD', 'Kapacitet SSD', 'SSD (GB)']],
        ['olx_attribute_id' => 247, 'bnc_attribute_aliases' => ['HDD', 'Hard disk', 'Kapacitet HDD']],
        ['olx_attribute_id' => 5156, 'bnc_attribute_aliases' => ['Garancija', 'Jamstvo']],
        ['olx_attribute_id' => 5180, 'bnc_attribute_aliases' => ['Model procesora', 'CPU model']],
    ],

    // Laptopi
    39 => [
        ['olx_attribute_id' => 264, 'bnc_attribute_aliases' => ['RAM', 'Memorija RAM', 'Memorija', 'Kapacitet RAM'], 'is_required_for_publish' => true],
        ['olx_attribute_id' => 261, 'attribute_definition_id' => 1170, 'bnc_attribute_aliases' => ['Operativni sistem', 'Operativni Sustav', 'Operativni sistem - uređaj', 'Operativni Sustavi', 'OS'], 'is_required_for_publish' => true, 'value_mappings' => [
            'FreeDOS' => 'Nema', 'DOS' => 'Nema', 'Bez OS-a' => 'Nema', 'Bez OS' => 'Nema', 'Without OS' => 'Nema', 'No OS' => 'Nema',
            'Win 11' => 'Win 11', 'Windows 11' => 'Win 11', 'Win 10' => 'Win 10', 'Windows 10' => 'Win 10',
            'macOS' => 'Mac OS', 'Mac OS' => 'Mac OS', 'Apple Mac OS' => 'Mac OS', 'Linux' => 'Linux', 'Ostalo' => 'Nema',
        ]],
        ['olx_attribute_id' => 4784, 'bnc_attribute_aliases' => ['SSD', 'Kapacitet SSD', 'SSD (GB)', 'SSD kapacitet']],
        ['olx_attribute_id' => 2465, 'bnc_attribute_aliases' => ['SSD'], 'default_value' => 'Ne'],
        ['olx_attribute_id' => 265, 'attribute_definition_id' => 349, 'bnc_attribute_aliases' => ['Dijagonala ekrana', 'Dijagonala', 'Display', 'Ekran', 'Veličina ekrana', 'Veličina (inch)'], 'is_required_for_publish' => true],
        ['olx_attribute_id' => 262, 'bnc_attribute_aliases' => ['Procesor', 'CPU'], 'is_required_for_publish' => true],
        ['olx_attribute_id' => 3872, 'bnc_attribute_aliases' => ['Vrsta graficke', 'Grafička kartica tip'], 'default_value' => 'Integrisana', 'is_required_for_publish' => true],
        ['olx_attribute_id' => 1159, 'bnc_attribute_aliases' => ['Brzina procesora', 'Brzina procesora (GHz)']],
        ['olx_attribute_id' => 5054, 'bnc_attribute_aliases' => ['Model procesora', 'CPU model']],
        ['olx_attribute_id' => 263, 'bnc_attribute_aliases' => ['HDD', 'Hard disk']],
        ['olx_attribute_id' => 5157, 'bnc_attribute_aliases' => ['Garancija', 'Jamstvo']],
        ['olx_attribute_id' => 5252, 'bnc_attribute_aliases' => ['Rezolucija', 'Rezolucija ekrana']],
        ['olx_attribute_id' => 274, 'bnc_attribute_aliases' => ['Bluetooth']],
        ['olx_attribute_id' => 273, 'bnc_attribute_aliases' => ['Wi-Fi', 'Wireless', 'WiFi']],
    ],

    // Klime
    775 => [
        ['olx_attribute_id' => 7277, 'bnc_attribute_aliases' => ['Tip', 'Tip klime', 'Inverter'], 'default_value' => 'Inverter', 'is_required_for_publish' => true, 'value_mappings' => ['Inverter' => 'Inverter', 'On/Off' => 'On/Off', 'On Off' => 'On/Off']],
        ['olx_attribute_id' => 4740, 'bnc_attribute_aliases' => ['Energetska klasa hladenje', 'Energetska klasa', 'Energ. klasa'], 'default_value' => 'A', 'is_required_for_publish' => true],
        ['olx_attribute_id' => 4738, 'bnc_attribute_aliases' => ['Kapacitet hladenja', 'Kapacitet hladjenja', 'Snaga hladenja', 'kW'], 'default_value' => '2.5', 'is_required_for_publish' => true],
        ['olx_attribute_id' => 4744, 'bnc_attribute_aliases' => ['Prostor', 'Površina', 'Kvadratura', 'm2', 'm²'], 'default_value' => '25', 'is_required_for_publish' => true],
        ['olx_attribute_id' => 4739, 'bnc_attribute_aliases' => ['Kapacitet grijanja', 'Snaga grijanja']],
        ['olx_attribute_id' => 4741, 'bnc_attribute_aliases' => ['Energetska klasa grijanje']],
        ['olx_attribute_id' => 7164, 'default_value' => 'Prodaja'],
    ],

    // Električni trotineti / romobili
    2529 => [
        ['olx_attribute_id' => 7204, 'default_value' => 'Prodaja', 'is_required_for_publish' => true],
    ],

    // Printeri
    166 => [
        ['olx_attribute_id' => 7522, 'bnc_attribute_aliases' => ['Tip uređaja', 'Tip', 'Vrsta'], 'default_value' => 'Printer', 'is_required_for_publish' => true, 'value_mappings' => ['Multifunkcijski' => 'Printer', 'MFP' => 'Printer', 'Skener' => 'Skener', 'Kopir' => 'Kopir aparat']],
        ['olx_attribute_id' => 4788, 'bnc_attribute_aliases' => ['Vrsta printera', 'Tehnologija ispisa', 'Tip printera']],
        ['olx_attribute_id' => 3086, 'bnc_attribute_aliases' => ['Multifunkcijski', 'MFP']],
        ['olx_attribute_id' => 5159, 'bnc_attribute_aliases' => ['Garancija', 'Jamstvo']],
    ],

    // Video nadzor
    816 => [
        ['olx_attribute_id' => 7445, 'bnc_attribute_aliases' => ['Rezolucija', 'Video rezolucija', 'Max rezolucija'], 'default_value' => '1080p', 'is_required_for_publish' => true, 'value_mappings' => ['Full HD' => '1080p', 'FHD' => '1080p', 'HD' => '720p', '4K' => '4K', '8K' => '8K', '1080P' => '1080p']],
        ['olx_attribute_id' => 7446, 'bnc_attribute_aliases' => ['WiFi', 'Wi-Fi', 'Wireless']],
        ['olx_attribute_id' => 7449, 'bnc_attribute_aliases' => ['PIR', 'Senzor pokreta']],
    ],

    // Tastature
    170 => [
        ['olx_attribute_id' => 2170, 'bnc_attribute_aliases' => ['Priključak', 'Konekcija', 'Interfejs', 'Tip priključka'], 'default_value' => 'USB', 'is_required_for_publish' => true, 'value_mappings' => ['Bežični' => 'Wireless', 'Bezicni' => 'Wireless', 'Bluetooth' => 'Wireless', 'BT' => 'Wireless']],
        ['olx_attribute_id' => 3100, 'bnc_attribute_aliases' => ['Gaming']],
        ['olx_attribute_id' => 5269, 'bnc_attribute_aliases' => ['Mehanička', 'Tip prekidača']],
        ['olx_attribute_id' => 1123, 'bnc_attribute_aliases' => ['Boja']],
    ],

    // Miševi
    162 => [
        ['olx_attribute_id' => 2339, 'bnc_attribute_aliases' => ['Priključak', 'Konekcija', 'Interfejs'], 'default_value' => 'USB', 'is_required_for_publish' => true, 'value_mappings' => ['Bežični' => 'Wireless (bežični)', 'Bezicni' => 'Wireless (bežični)', 'Wireless' => 'Wireless (bežični)', 'BT' => 'Wireless (bežični)', 'Bluetooth' => 'Wireless (bežični)']],
        ['olx_attribute_id' => 2321, 'bnc_attribute_aliases' => ['DPI', 'Rezolucija']],
        ['olx_attribute_id' => 2326, 'bnc_attribute_aliases' => ['Gaming']],
        ['olx_attribute_id' => 5161, 'bnc_attribute_aliases' => ['Garancija', 'Jamstvo']],
    ],

    // PC slušalice
    1499 => [
        ['olx_attribute_id' => 3178, 'bnc_attribute_aliases' => ['Tip slušalica', 'Vrsta', 'Tip'], 'default_value' => 'Na uho', 'is_required_for_publish' => true, 'value_mappings' => ['Over-ear' => 'Na uho', 'On-ear' => 'Oko uha', 'In-ear' => 'U uho', 'Earbuds' => 'U uho', 'Headset' => 'Na uho']],
        ['olx_attribute_id' => 3177, 'bnc_attribute_aliases' => ['Mikrofon', 'Sa mikrofonom']],
        ['olx_attribute_id' => 3180, 'bnc_attribute_aliases' => ['Wireless', 'Wi-Fi', 'Bluetooth', 'Bežične']],
        ['olx_attribute_id' => 3179, 'bnc_attribute_aliases' => ['Gaming']],
        ['olx_attribute_id' => 7978, 'bnc_attribute_aliases' => ['Boja']],
    ],

    // Projektori
    248 => [
        ['olx_attribute_id' => 7126, 'default_value' => 'Prodaja', 'is_required_for_publish' => true],
        ['olx_attribute_id' => 7521, 'default_value' => 'Projektori'],
        ['olx_attribute_id' => 2342, 'bnc_attribute_aliases' => ['Rezolucija', 'Native rezolucija']],
        ['olx_attribute_id' => 2341, 'bnc_attribute_aliases' => ['Domet', 'Distanca']],
        ['olx_attribute_id' => 3108, 'bnc_attribute_aliases' => ['3D']],
    ],

    // Televizori
    1748 => [
        ['olx_attribute_id' => 7525, 'bnc_attribute_aliases' => ['Tip panela', 'Tehnologija', 'Tip ekrana', 'Display tehnologija'], 'default_value' => 'LED LCD', 'is_required_for_publish' => true, 'value_mappings' => ['LED' => 'LED LCD', 'LCD' => 'LED LCD', 'QLED' => 'QLED', 'OLED' => 'OLED', 'Mini LED' => 'MINI LED']],
        ['olx_attribute_id' => 3457, 'attribute_definition_id' => 352, 'bnc_attribute_aliases' => ['Dijagonala TV-a (inch)', 'Dijagonala ekrana', 'Dijagonala', 'Veličina ekrana', 'Display', 'Veličina (inch)'], 'is_required_for_publish' => true],
        ['olx_attribute_id' => 3459, 'bnc_attribute_aliases' => ['Rezolucija', 'Native rezolucija'], 'default_value' => '4K', 'is_required_for_publish' => true, 'value_mappings' => ['3840x2160' => '4K', '1920x1080' => '1080p (full HD)', '1366x768' => '768p', 'UHD' => '4K', 'Full HD' => '1080p (full HD)', 'FHD' => '1080p (full HD)']],
        ['olx_attribute_id' => 7526, 'bnc_attribute_aliases' => ['Smart TV', 'Smart'], 'value_mappings' => ['Da' => 'Smart', 'Ne' => 'Non Smart']],
        ['olx_attribute_id' => 5255, 'bnc_attribute_aliases' => ['Operativni sistem', 'OS', 'Smart platforma'], 'value_mappings' => ['Android' => 'Android TV', 'Google TV' => 'Google TV', 'WebOS' => 'WebOS', 'Tizen' => 'Tizen']],
        ['olx_attribute_id' => 3470, 'bnc_attribute_aliases' => ['HDMI', 'Broj HDMI']],
        ['olx_attribute_id' => 5152, 'bnc_attribute_aliases' => ['Garancija', 'Jamstvo']],
    ],

    // Ventilatori
    776 => [
        ['olx_attribute_id' => 7167, 'default_value' => 'Prodaja', 'is_required_for_publish' => true],
    ],

    // Pametni satovi
    2076 => [
        ['olx_attribute_id' => 5060, 'bnc_attribute_aliases' => ['Operativni sistem', 'OS', 'Platforma'], 'default_value' => 'Android', 'is_required_for_publish' => true, 'value_mappings' => ['watchOS' => 'iOS', 'Apple' => 'iOS', 'iPhone' => 'iOS']],
        ['olx_attribute_id' => 5058, 'bnc_attribute_aliases' => ['Boja kaiša', 'Boja narukvice', 'Boja'], 'default_value' => 'Crna', 'is_required_for_publish' => true],
        ['olx_attribute_id' => 5067, 'attribute_definition_id' => 349, 'bnc_attribute_aliases' => ['Dijagonala ekrana', 'Dijagonala', 'Display', 'Veličina ekrana', 'Veličina (inch)']],
        ['olx_attribute_id' => 5069, 'bnc_attribute_aliases' => ['Kapacitet baterije', 'Baterija (mAh)']],
        ['olx_attribute_id' => 5071, 'bnc_attribute_aliases' => ['Vodootporan', 'Waterproof', 'IP']],
    ],

    // Prečišćivači zraka (fallback OLX kategorija)
    2464 => [
        ['olx_attribute_id' => 6746, 'bnc_attribute_aliases' => ['Tip', 'Vrsta', 'Vrsta uređaja'], 'default_value' => 'Cirkulacione pumpe', 'is_required_for_publish' => true],
        ['olx_attribute_id' => 7153, 'default_value' => 'Prodaja'],
    ],
];
