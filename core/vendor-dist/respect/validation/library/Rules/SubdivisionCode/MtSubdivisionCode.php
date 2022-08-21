<?php

/*
 * This file is part of Respect/Validation.
 *
 * (c) Alexandre Gomes Gaigalas <alexandre@gaigalas.net>
 *
 * For the full copyright and license information, please view the "LICENSE.md"
 * file that was distributed with this source code.
 */

namespace Respect\Validation\Rules\SubdivisionCode;

use Respect\Validation\Rules\AbstractSearcher;

/**
 * Validator for Malta subdivision code.
 *
 * ISO 3166-1 alpha-2: MT
 *
 * @link https://salsa.debian.org/iso-codes-team/iso-codes
 */
class MtSubdivisionCode extends AbstractSearcher
{
    public $haystack = [
        '01', // Attard
        '02', // Balzan
        '03', // Birgu
        '04', // Birkirkara
        '05', // Birżebbuġa
        '06', // Bormla
        '07', // Dingli
        '08', // Fgura
        '09', // Floriana
        '10', // Fontana
        '11', // Gudja
        '12', // Gżira
        '13', // Għajnsielem
        '14', // Għarb
        '15', // Għargħur
        '16', // Għasri
        '17', // Għaxaq
        '18', // Ħamrun
        '19', // Iklin
        '20', // Isla
        '21', // Kalkara
        '22', // Kerċem
        '23', // Kirkop
        '24', // Lija
        '25', // Luqa
        '26', // Marsa
        '27', // Marsaskala
        '28', // Marsaxlokk
        '29', // Mdina
        '30', // Mellieħa
        '31', // Mġarr
        '32', // Mosta
        '33', // Mqabba
        '34', // Msida
        '35', // Mtarfa
        '36', // Munxar
        '37', // Nadur
        '38', // Naxxar
        '39', // Paola
        '40', // Pembroke
        '41', // Pietà
        '42', // Qala
        '43', // Qormi
        '44', // Qrendi
        '45', // Rabat Għawdex
        '46', // Rabat Malta
        '47', // Safi
        '48', // San Ġiljan
        '49', // San Ġwann
        '50', // San Lawrenz
        '51', // San Pawl il-Baħar
        '52', // Sannat
        '53', // Santa Luċija
        '54', // Santa Venera
        '55', // Siġġiewi
        '56', // Sliema
        '57', // Swieqi
        '58', // Ta’ Xbiex
        '59', // Tarxien
        '60', // Valletta
        '61', // Xagħra
        '62', // Xewkija
        '63', // Xgħajra
        '64', // Żabbar
        '65', // Żebbuġ Għawdex
        '66', // Żebbuġ Malta
        '67', // Żejtun
        '68', // Żurrieq
    ];

    public $compareIdentical = true;
}
