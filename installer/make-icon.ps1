<#
.SYNOPSIS
  Draw Alice's icon (an original doll motif in Alice Margatroid's colours:
  blonde hair, red hairband, blue dress, white capelet) and write a multi-size
  .ico with PNG entries. No external images, nothing copied from anywhere.

  powershell -ExecutionPolicy Bypass -File installer\make-icon.ps1 [-Out installer\alice.ico] [-Png preview.png]
#>
[CmdletBinding()]
param(
    [string] $Out = '',
    [string] $Png = '',
    [int] $Variant = 1,  # 1 = doll bust, 2 = long hair + capelet + Shanghai doll
    [string] $Source = ''  # image file to build the icon from (default: installer\alice.png if it exists)
)
$ErrorActionPreference = 'Stop'
if ($Out -eq '') { $Out = Join-Path (Split-Path -Parent $MyInvocation.MyCommand.Path) 'alice.ico' }
Add-Type -AssemblyName System.Drawing

function Draw-Alice([int] $size) {
    if ($Variant -eq 2) { return (Draw-Alice2 $size) }
    $bmp = New-Object System.Drawing.Bitmap $size, $size
    $g = [System.Drawing.Graphics]::FromImage($bmp)
    $g.SmoothingMode = 'AntiAlias'
    $g.InterpolationMode = 'HighQualityBicubic'
    $g.Clear([System.Drawing.Color]::Transparent)
    $s = $size / 256.0
    function P([double] $x, [double] $y) { New-Object System.Drawing.PointF ($x * $s), ($y * $s) }
    function Brush([int] $r, [int] $gg, [int] $b) { New-Object System.Drawing.SolidBrush ([System.Drawing.Color]::FromArgb($r, $gg, $b)) }
    function Pen([int] $r, [int] $gg, [int] $b, [double] $w) { New-Object System.Drawing.Pen ([System.Drawing.Color]::FromArgb($r, $gg, $b)), ($w * $s) }

    $skin = Brush 255 228 205
    $hair = Brush 245 205 90
    $hairDark = Brush 222 178 60
    $dress = Brush 72 110 200
    $dressDark = Brush 52 82 160
    $white = Brush 250 250 252
    $red = Brush 214 48 60
    $ink = Pen 60 50 70 3

    # background disc (soft blue, like a doll's display stand)
    $g.FillEllipse((Brush 225 234 250), (4 * $s), (4 * $s), (248 * $s), (248 * $s))
    $g.DrawEllipse((Pen 120 150 210 4), (6 * $s), (6 * $s), (244 * $s), (244 * $s))

    # dress (bell shape) and white capelet
    $dressPts = @((P 128 120), (P 88 160), (P 60 232), (P 196 232), (P 168 160))
    $g.FillPolygon($dressDark, $dressPts)
    $g.FillPolygon($dress, @((P 128 124), (P 96 162), (P 76 226), (P 180 226), (P 160 162)))
    $g.FillPolygon($white, @((P 128 118), (P 92 150), (P 128 176), (P 164 150)))
    $g.FillEllipse($red, (121 * $s), (146 * $s), (14 * $s), (14 * $s))   # ribbon at the collar

    # hair back (long, blonde)
    $g.FillEllipse($hairDark, (56 * $s), (46 * $s), (144 * $s), (150 * $s))
    # head
    $g.FillEllipse($skin, (72 * $s), (58 * $s), (112 * $s), (112 * $s))
    # fringe
    $g.FillPie($hair, (66 * $s), (40 * $s), (124 * $s), (112 * $s), 190, 160)
    $g.FillPolygon($hair, @((P 70 100), (P 86 70), (P 100 104)))
    $g.FillPolygon($hair, @((P 186 100), (P 170 70), (P 156 104)))
    # red hairband with a small bow
    $band = Pen 214 48 60 10
    $g.DrawArc($band, (70 * $s), (44 * $s), (116 * $s), (108 * $s), 205, 130)
    $g.FillEllipse($red, (154 * $s), (46 * $s), (22 * $s), (18 * $s))
    $g.FillEllipse($red, (170 * $s), (56 * $s), (20 * $s), (16 * $s))
    # eyes (blue) and a small smile
    $g.FillEllipse($dressDark, (98 * $s), (104 * $s), (14 * $s), (20 * $s))
    $g.FillEllipse($dressDark, (144 * $s), (104 * $s), (14 * $s), (20 * $s))
    $g.FillEllipse($white, (101 * $s), (107 * $s), (5 * $s), (6 * $s))
    $g.FillEllipse($white, (147 * $s), (107 * $s), (5 * $s), (6 * $s))
    $g.DrawArc((Pen 180 90 100 3), (118 * $s), (126 * $s), (20 * $s), (12 * $s), 20, 140)
    $g.Dispose()
    return $bmp
}

function Draw-Alice2([int] $size) {
    $bmp = New-Object System.Drawing.Bitmap $size, $size
    $g = [System.Drawing.Graphics]::FromImage($bmp)
    $g.SmoothingMode = 'AntiAlias'
    $g.Clear([System.Drawing.Color]::Transparent)
    $s = $size / 256.0
    function P([double] $x, [double] $y) { New-Object System.Drawing.PointF ($x * $s), ($y * $s) }
    function Brush([int] $r, [int] $gg, [int] $b) { New-Object System.Drawing.SolidBrush ([System.Drawing.Color]::FromArgb($r, $gg, $b)) }
    function Pen([int] $r, [int] $gg, [int] $b, [double] $w) { New-Object System.Drawing.Pen ([System.Drawing.Color]::FromArgb($r, $gg, $b)), ($w * $s) }
    function E($brush, [double] $x, [double] $y, [double] $w, [double] $h) { $g.FillEllipse($brush, ($x * $s), ($y * $s), ($w * $s), ($h * $s)) }

    $skin = Brush 255 228 205
    $hair = Brush 248 212 100
    $hairDark = Brush 226 182 64
    $dress = Brush 78 118 210
    $dressDark = Brush 50 80 158
    $white = Brush 252 252 254
    $red = Brush 214 48 60
    $blue = Brush 60 100 190

    # rounded-square background, soft lavender-blue
    $path = New-Object System.Drawing.Drawing2D.GraphicsPath
    $r = 56 * $s; $w = 248 * $s; $o = 4 * $s
    $path.AddArc($o, $o, $r, $r, 180, 90); $path.AddArc($o + $w - $r, $o, $r, $r, 270, 90)
    $path.AddArc($o + $w - $r, $o + $w - $r, $r, $r, 0, 90); $path.AddArc($o, $o + $w - $r, $r, $r, 90, 90); $path.CloseFigure()
    $g.FillPath((Brush 214 224 246), $path)

    # long hair behind (down to the bottom), with the ends curling out
    $g.FillPolygon($hairDark, @((P 128 44), (P 62 100), (P 48 236), (P 90 236), (P 128 200), (P 166 236), (P 208 236), (P 194 100)))
    E $hairDark 40 196 52 44
    E $hairDark 164 196 52 44

    # capelet (white) over the dress (blue)
    $g.FillPolygon($dressDark, @((P 128 150), (P 86 190), (P 78 236), (P 178 236), (P 170 190)))
    $g.FillPolygon($dress, @((P 128 154), (P 94 192), (P 88 230), (P 168 230), (P 162 192)))
    $g.FillPolygon($white, @((P 128 146), (P 84 178), (P 100 196), (P 128 212), (P 156 196), (P 172 178)))
    E $red 120 176 16 16                                     # red ribbon on the capelet
    $g.FillPolygon($red, @((P 108 184), (P 120 180), (P 120 190)))
    $g.FillPolygon($red, @((P 148 184), (P 136 180), (P 136 190)))

    # head and face
    E $skin 76 56 104 108
    $g.FillPie($hair, (70 * $s), (38 * $s), (116 * $s), (104 * $s), 188, 164)   # fringe
    $g.FillPolygon($hair, @((P 74 96), (P 90 66), (P 104 104)))
    $g.FillPolygon($hair, @((P 182 96), (P 166 66), (P 152 104)))
    $g.FillPolygon($hair, @((P 118 70), (P 128 50), (P 140 72)))
    # red hairband with a bow on the right
    $g.DrawArc((Pen 214 48 60 9), (72 * $s), (42 * $s), (112 * $s), (100 * $s), 206, 128)
    E $red 160 40 22 18
    E $red 176 50 20 16
    E (Brush 170 30 42) 170 50 8 8
    # eyes (blue), highlights, mouth
    E $blue 100 100 14 22
    E $blue 142 100 14 22
    E $white 103 103 5 7
    E $white 145 103 5 7
    $g.DrawArc((Pen 180 90 100 3), (118 * $s), (124 * $s), (20 * $s), (12 * $s), 20, 140)

    # Shanghai doll, bottom right: red dress, white apron, blonde, tiny ribbon
    E $hairDark 190 150 44 40
    $g.FillPolygon((Brush 200 40 56), @((P 212 186), (P 196 206), (P 192 236), (P 232 236), (P 228 206)))
    $g.FillPolygon($white, @((P 212 190), (P 203 210), (P 201 232), (P 223 232), (P 221 210)))
    E $skin 196 158 32 34
    $g.FillPie($hair, (194 * $s), (152 * $s), (36 * $s), (30 * $s), 190, 160)
    E $red 220 150 10 8
    E $blue 204 172 4 7
    E $blue 216 172 4 7
    $g.Dispose()
    return $bmp
}

# If installer\alice.png exists (an image you have the rights to use: your own
# drawing, a commissioned one, or one under a licence that allows it), the icon
# is made from it instead of the drawn doll. Square images look best.
$customPng = if ($Source -ne '') { $Source } else { Join-Path (Split-Path -Parent $MyInvocation.MyCommand.Path) 'alice.png' }

function Get-Frame([int] $size) {
    if (-not (Test-Path $customPng)) { return (Draw-Alice $size) }
    $src = [System.Drawing.Image]::FromFile($customPng)
    $bmp = New-Object System.Drawing.Bitmap $size, $size
    $g = [System.Drawing.Graphics]::FromImage($bmp)
    $g.Clear([System.Drawing.Color]::Transparent)
    if ($src.Width -lt $size) {
        # pixel art scaled up: keep the pixels crisp instead of blurring them
        $g.InterpolationMode = 'NearestNeighbor'
        $g.PixelOffsetMode = 'Half'
    } else {
        $g.SmoothingMode = 'AntiAlias'
        $g.InterpolationMode = 'HighQualityBicubic'
    }
    # centre-crop to a square, then scale
    $side = [Math]::Min($src.Width, $src.Height)
    $crop = New-Object System.Drawing.Rectangle (([int](($src.Width - $side) / 2)), ([int](($src.Height - $side) / 2)), $side, $side)
    $g.DrawImage($src, (New-Object System.Drawing.Rectangle 0, 0, $size, $size), $crop, [System.Drawing.GraphicsUnit]::Pixel)
    $g.Dispose(); $src.Dispose()
    return $bmp
}

# Classic 32-bit DIB entry (BITMAPINFOHEADER + bottom-up BGRA rows + AND mask).
# .NET's System.Drawing.Icon only decodes these reliably; PNG entries are kept
# for 256 px only, where Explorer expects them.
function Get-DibEntry([System.Drawing.Bitmap] $bmp) {
    $w = $bmp.Width; $h = $bmp.Height
    $ms = New-Object System.IO.MemoryStream
    $bw = New-Object System.IO.BinaryWriter $ms
    $bw.Write([uint32] 40); $bw.Write([int32] $w); $bw.Write([int32] ($h * 2)); $bw.Write([uint16] 1); $bw.Write([uint16] 32)
    $bw.Write([uint32] 0); $bw.Write([uint32] ($w * $h * 4)); $bw.Write([int32] 0); $bw.Write([int32] 0); $bw.Write([uint32] 0); $bw.Write([uint32] 0)
    for ($y = $h - 1; $y -ge 0; $y--) {
        for ($x = 0; $x -lt $w; $x++) {
            $c = $bmp.GetPixel($x, $y)
            $bw.Write([byte] $c.B); $bw.Write([byte] $c.G); $bw.Write([byte] $c.R); $bw.Write([byte] $c.A)
        }
    }
    $maskRow = [int][Math]::Ceiling($w / 32.0) * 4
    for ($y = $h - 1; $y -ge 0; $y--) {
        $row = New-Object byte[] $maskRow
        for ($x = 0; $x -lt $w; $x++) {
            $i = [int][Math]::Floor($x / 8)   # [int] alone rounds in PowerShell
            if ($bmp.GetPixel($x, $y).A -lt 128) { $row[$i] = $row[$i] -bor (0x80 -shr ($x % 8)) }
        }
        $bw.Write($row)
    }
    $bw.Flush()
    return , [byte[]] $ms.ToArray()   # leading comma: keep the array intact through the pipeline
}

function Write-Ico([string] $path, [int[]] $sizes) {
    $images = @()
    foreach ($size in $sizes) {
        $bmp = Get-Frame $size
        if ($size -ge 256) {
            $ms = New-Object System.IO.MemoryStream
            $bmp.Save($ms, [System.Drawing.Imaging.ImageFormat]::Png)
            $bytes = [byte[]] $ms.ToArray()
        } else {
            $bytes = [byte[]] (Get-DibEntry $bmp)
        }
        $images += , @{ size = $size; bytes = $bytes }
        $bmp.Dispose()
    }
    $fs = [System.IO.File]::Create($path)
    $w = New-Object System.IO.BinaryWriter $fs
    $w.Write([uint16] 0); $w.Write([uint16] 1); $w.Write([uint16] $images.Count)
    $offset = 6 + 16 * $images.Count
    foreach ($img in $images) {
        $dim = if ($img.size -ge 256) { 0 } else { $img.size }
        $w.Write([byte] $dim); $w.Write([byte] $dim); $w.Write([byte] 0); $w.Write([byte] 0)
        $w.Write([uint16] 1); $w.Write([uint16] 32)
        $w.Write([uint32] $img.bytes.Length); $w.Write([uint32] $offset)
        $offset += $img.bytes.Length
    }
    foreach ($img in $images) { $w.Write([byte[]] $img.bytes) }
    $w.Flush(); $fs.Close()
}

Write-Ico $Out @(16, 24, 32, 48, 64, 128, 256)
Write-Host "wrote $Out"
if (Test-Path $customPng) { Write-Host "(made from $customPng)" }
if ($Png -ne '') {
    $bmp = Get-Frame 256
    $bmp.Save($Png, [System.Drawing.Imaging.ImageFormat]::Png)
    $bmp.Dispose()
    Write-Host "wrote $Png"
}
