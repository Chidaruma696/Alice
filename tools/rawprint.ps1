<#
.SYNOPSIS
  Send a file to a named Windows printer as a RAW document, through the spooler.

.DESCRIPTION
  Alice's raw-print helper. Uses winspool.drv (OpenPrinter / StartDocPrinter with
  datatype RAW / WritePrinter) so an ESC/P or ESC/POS file reaches a local USB
  printer by its installed name, with no printer sharing and no driver rendering.

  Invoked by Alice as:
    powershell -NoProfile -NonInteractive -ExecutionPolicy Bypass -File rawprint.ps1 -PrinterName "EPSON LX-350" -Path C:\...\job.prn

.OUTPUTS
  Exit code 0 on success. On failure the Win32 error text is written to stderr and
  the exit code is 1 (print error) or 2 (bad arguments / file not found).
#>
[CmdletBinding()]
param(
    [Parameter(Mandatory = $true)] [string] $PrinterName,
    [Parameter(Mandatory = $true)] [string] $Path,
    [string] $DocumentName = 'Alice'
)

$ErrorActionPreference = 'Stop'
try { [Console]::OutputEncoding = [System.Text.Encoding]::UTF8 } catch { }

if (-not (Test-Path -LiteralPath $Path -PathType Leaf)) {
    [Console]::Error.WriteLine("file not found: $Path")
    exit 2
}

$source = @'
using System;
using System.ComponentModel;
using System.IO;
using System.Runtime.InteropServices;

public static class AliceRawPrinter
{
    [StructLayout(LayoutKind.Sequential, CharSet = CharSet.Unicode)]
    public struct DOCINFOW
    {
        [MarshalAs(UnmanagedType.LPWStr)] public string pDocName;
        [MarshalAs(UnmanagedType.LPWStr)] public string pOutputFile;
        [MarshalAs(UnmanagedType.LPWStr)] public string pDataType;
    }

    [DllImport("winspool.drv", EntryPoint = "OpenPrinterW", CharSet = CharSet.Unicode, SetLastError = true)]
    static extern bool OpenPrinter(string printerName, out IntPtr handle, IntPtr defaults);

    [DllImport("winspool.drv", SetLastError = true)]
    static extern bool ClosePrinter(IntPtr handle);

    [DllImport("winspool.drv", EntryPoint = "StartDocPrinterW", CharSet = CharSet.Unicode, SetLastError = true)]
    static extern int StartDocPrinter(IntPtr handle, int level, ref DOCINFOW docInfo);

    [DllImport("winspool.drv", SetLastError = true)]
    static extern bool EndDocPrinter(IntPtr handle);

    [DllImport("winspool.drv", SetLastError = true)]
    static extern bool StartPagePrinter(IntPtr handle);

    [DllImport("winspool.drv", SetLastError = true)]
    static extern bool EndPagePrinter(IntPtr handle);

    [DllImport("winspool.drv", SetLastError = true)]
    static extern bool WritePrinter(IntPtr handle, byte[] buffer, int count, out int written);

    static Exception LastError(string step)
    {
        int code = Marshal.GetLastWin32Error();
        return new Win32Exception(code, step + ": " + new Win32Exception(code).Message + " (error " + code + ")");
    }

    public static void Send(string printer, string path, string documentName)
    {
        byte[] bytes = File.ReadAllBytes(path);
        IntPtr handle;
        if (!OpenPrinter(printer, out handle, IntPtr.Zero))
            throw LastError("OpenPrinter '" + printer + "'");
        try
        {
            DOCINFOW info = new DOCINFOW();
            info.pDocName = documentName;
            info.pOutputFile = null;
            info.pDataType = "RAW";
            if (StartDocPrinter(handle, 1, ref info) == 0)
                throw LastError("StartDocPrinter");
            try
            {
                if (!StartPagePrinter(handle))
                    throw LastError("StartPagePrinter");
                int written;
                if (!WritePrinter(handle, bytes, bytes.Length, out written))
                    throw LastError("WritePrinter");
                if (written != bytes.Length)
                    throw new IOException("WritePrinter wrote " + written + " of " + bytes.Length + " bytes");
                EndPagePrinter(handle);
            }
            finally
            {
                EndDocPrinter(handle);
            }
        }
        finally
        {
            ClosePrinter(handle);
        }
    }
}
'@

try {
    if (-not ('AliceRawPrinter' -as [type])) {
        Add-Type -TypeDefinition $source -Language CSharp
    }
    [AliceRawPrinter]::Send($PrinterName, $Path, $DocumentName)
    exit 0
}
catch {
    $e = $_.Exception
    while ($e.InnerException -ne $null) { $e = $e.InnerException }
    [Console]::Error.WriteLine($e.Message)
    exit 1
}
