// Tiles a two-line watermark across every page of a PDF, in place in the file
// rather than over it on screen.
//
// Run by PdfWatermarker through `mutool run`. The text arrives as arguments
// rather than being written into this script, so nothing a title or an email
// address contains can become code here.
//
// Written for mujs, which is MuPDF's JavaScript engine and not a browser one:
// no regular-expression literals, no const/let, no arrow functions.
//
// The text must already be CP1252 bytes, not UTF-8: the font here is a
// standard Helvetica, whose encoding is one byte per glyph, so a UTF-8 middle
// dot arrives as two characters and prints as one. PdfWatermarker converts.
//
//   mutool run watermark.js <input> <output> <line1> <line2> [opacity] [size] [offset] [logo]
//
// With a logo (a PNG path), each mark is the logo with line1 under it.

var input   = scriptArgs[0];
var output  = scriptArgs[1];
var line1   = scriptArgs[2] || '';
var line2   = scriptArgs[3] || '';
var opacity = scriptArgs[4] ? parseFloat(scriptArgs[4]) : 0.13;
var size    = scriptArgs[5] ? parseFloat(scriptArgs[5]) : 11;
// Shifts the grid, so a document stamped twice does not print the second mark
// on top of the first and leave both unreadable.
var offset  = scriptArgs[6] ? parseFloat(scriptArgs[6]) : 0;
var logoPath = scriptArgs[7] || '';

// -22 degrees, as the matrix PDF wants: cos, sin, -sin, cos.
var COS = 0.927, SIN = 0.375;
var STEP = 300;               // gap between marks, in points

var doc  = new PDFDocument(input);
// "Latin" is CP1252, which is the encoding esc() below writes bytes for.
var font = doc.addSimpleFont(new Font('Helvetica'), 'Latin');
var pages = doc.countPages();

// Added once and shared by every page, so the file grows by one image, not one
// per page. A logo that will not load leaves the mark as text alone.
var logo = null;
if (logoPath) {
    try { logo = doc.addImage(new Image(logoPath)); } catch (e) { logo = null; }
}
var LOGO = size * 4.2;           // drawn size of the logo, in points

// The few CP1252 bytes that are not simply the Unicode code point. Everything
// from 0xA0 to 0xFF matches Latin-1 and needs no entry, which covers the
// accents in the names this archive actually holds - Cariño, Peña.
var CP1252 = {
    0x20AC: 0x80, 0x201A: 0x82, 0x0192: 0x83, 0x201E: 0x84, 0x2026: 0x85,
    0x2020: 0x86, 0x2021: 0x87, 0x02C6: 0x88, 0x2030: 0x89, 0x0160: 0x8A,
    0x2039: 0x8B, 0x0152: 0x8C, 0x017D: 0x8E, 0x2018: 0x91, 0x2019: 0x92,
    0x201C: 0x93, 0x201D: 0x94, 0x2022: 0x95, 0x2013: 0x96, 0x2014: 0x97,
    0x02DC: 0x98, 0x2122: 0x99, 0x0161: 0x9A, 0x203A: 0x9B, 0x0153: 0x9C,
    0x017E: 0x9E, 0x0178: 0x9F
};

// Turns a string into the bytes a PDF string literal wants.
//
// Two things have to happen here and neither can happen earlier. A literal ends
// at the first unescaped bracket, so a title carrying one would truncate the
// mark and corrupt the content stream. And the font is a standard Helvetica,
// which is one byte per glyph, while a mujs string is UTF-8 - writing its
// characters through unchanged printed the middle dot in "CSPC ARCHIVE · NOT
// FOR REDISTRIBUTION" as two glyphs, "Â·". Converting on the PHP side does not
// help: the argument is re-encoded on its way into this process, so UTF-8 is
// what arrives whatever was sent. Non-ASCII goes out as an octal escape, which
// is the one form no layer in between can reinterpret.
function esc(s) {
    var out = '', i, code, byte, oct;
    for (i = 0; i < s.length; i++) {
        code = s.charCodeAt(i);

        if (code === 40 || code === 41 || code === 92) {     // ( ) \
            out += '\\' + s.charAt(i);
        } else if (code >= 32 && code < 127) {
            out += s.charAt(i);
        } else {
            byte = CP1252[code] !== undefined ? CP1252[code] : (code < 256 ? code : -1);
            if (byte >= 0) {
                oct = byte.toString(8);
                while (oct.length < 3) { oct = '0' + oct; }
                out += '\\' + oct;
            }
            // Anything with no single-byte form is dropped: a mark with a gap
            // in it still identifies the reader, an unbalanced literal does not.
        }
    }
    return out;
}

// MediaBox is inheritable, so a page that does not carry one takes its parent's.
function boxOf(page) {
    var o = page, depth = 0, mb;
    while (o && o.isDictionary() && depth++ < 32) {
        mb = o.get('MediaBox');
        if (mb && mb.isArray() && mb.length === 4) {
            return [mb.get(0).asNumber(), mb.get(1).asNumber(),
                    mb.get(2).asNumber(), mb.get(3).asNumber()];
        }
        o = o.get('Parent');
    }
    return [0, 0, 612, 792];        // US Letter, the safe assumption
}

// Fetch a dictionary from a parent, creating it if it is missing or malformed.
function dictAt(parent, key) {
    var d = parent.get(key);
    if (!d || !d.isDictionary()) {
        d = doc.addObject(doc.newDictionary());
        parent.put(key, d);
    }
    return d;
}

for (var i = 0; i < pages; i++) {
    var page = doc.findPage(i);
    var box  = boxOf(page);
    var w = box[2] - box[0];
    var h = box[3] - box[1];

    var res   = dictAt(page, 'Resources');
    var fonts = dictAt(res, 'Font');
    fonts.put('CBAMSWM', font);

    // Transparency has to come from an ExtGState; there is no operator for it.
    var egs = dictAt(res, 'ExtGState');
    var gs  = doc.newDictionary();
    gs.put('ca', opacity);
    gs.put('CA', opacity);
    egs.put('CBAMSGS', doc.addObject(gs));

    if (logo) {
        dictAt(res, 'XObject').put('CBAMSLOGO', logo);
    }

    // The leading Q closes the q that is prepended to the original content
    // below. Without that pairing the mark inherits whatever clip, transform or
    // colour the page's own stream happened to leave set, which on a real
    // document means it lands somewhere unpredictable or not at all.
    var ops = 'Q q /CBAMSGS gs 0.06 0.14 0.31 rg /CBAMSWM ' + size + ' Tf\n';

    for (var y = 40 + offset; y < h + STEP; y += STEP) {
        for (var x = 20 + offset; x < w + STEP; x += STEP) {
            var px  = (x + box[0]).toFixed(1);
            var py  = (y + box[1]).toFixed(1);
            var py2 = (y + box[1] - size - 3).toFixed(1);
            var m   = COS + ' ' + (-SIN) + ' ' + SIN + ' ' + COS + ' ';

            if (logo) {
                // Above the text, in the same rotated frame: the matrix maps the
                // unit square the image is drawn in to a LOGO-sized square
                // whose corner sits size + 4 points up the rotated y axis.
                var lift = size + 4;
                var lx = (x + box[0] + SIN * lift).toFixed(1);
                var ly = (y + box[1] + COS * lift).toFixed(1);
                ops += 'q ' + (LOGO * COS).toFixed(2) + ' ' + (-LOGO * SIN).toFixed(2) + ' '
                     + (LOGO * SIN).toFixed(2) + ' ' + (LOGO * COS).toFixed(2) + ' '
                     + lx + ' ' + ly + ' cm /CBAMSLOGO Do Q\n';
            }
            if (line1) {
                ops += 'BT ' + m + px + ' ' + py + ' Tm (' + esc(line1) + ') Tj ET\n';
            }
            if (line2) {
                ops += 'BT ' + m + px + ' ' + py2 + ' Tm (' + esc(line2) + ') Tj ET\n';
            }
        }
    }
    ops += 'Q\n';

    var pre  = doc.addStream('q\n', null);
    var post = doc.addStream(ops, null);

    // Contents is either one stream or an array of them, and the array form has
    // to be preserved in order - the pages are drawn by concatenation.
    var contents = page.get('Contents');
    var arr = doc.newArray();
    arr.push(pre);
    if (contents && contents.isArray()) {
        for (var k = 0; k < contents.length; k++) {
            arr.push(contents.get(k));
        }
    } else if (contents) {
        arr.push(contents);
    }
    arr.push(post);
    page.put('Contents', doc.addObject(arr));
}

doc.save(output, 'compress');
