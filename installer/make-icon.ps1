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
    [string] $Png = ''
)
$ErrorActionPreference = 'Stop'
if ($Out -eq '') { $Out = Join-Path (Split-Path -Parent $MyInvocation.MyCommand.Path) 'alice.ico' }
Add-Type -AssemblyName System.Drawing

function Draw-Alice([int] $size) {
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

# If installer\alice.png exists (an image you have the rights to use: your own
# drawing, a commissioned one, or one under a licence that allows it), the icon
# is made from it instead of the drawn doll. Square images look best.
$customPng = Join-Path (Split-Path -Parent $MyInvocation.MyCommand.Path) 'alice.png'

function Get-Frame([int] $size) {
    if (-not (Test-Path $customPng)) { return (Draw-Alice $size) }
    $src = [System.Drawing.Image]::FromFile($customPng)
    $bmp = New-Object System.Drawing.Bitmap $size, $size
    $g = [System.Drawing.Graphics]::FromImage($bmp)
    $g.SmoothingMode = 'AntiAlias'
    $g.InterpolationMode = 'HighQualityBicubic'
    $g.Clear([System.Drawing.Color]::Transparent)
    # centre-crop to a square, then scale
    $side = [Math]::Min($src.Width, $src.Height)
    $crop = New-Object System.Drawing.Rectangle (([int](($src.Width - $side) / 2)), ([int](($src.Height - $side) / 2)), $side, $side)
    $g.DrawImage($src, (New-Object System.Drawing.Rectangle 0, 0, $size, $size), $crop, [System.Drawing.GraphicsUnit]::Pixel)
    $g.Dispose(); $src.Dispose()
    return $bmp
}

function Write-Ico([string] $path, [int[]] $sizes) {
    $images = @()
    foreach ($size in $sizes) {
        $bmp = Get-Frame $size
        $ms = New-Object System.IO.MemoryStream
        $bmp.Save($ms, [System.Drawing.Imaging.ImageFormat]::Png)
        $images += , @{ size = $size; bytes = $ms.ToArray() }
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
    foreach ($img in $images) { $w.Write($img.bytes) }
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
