param(
    [string] $PhpPath = 'C:\xampp\php\php.exe'
)

$phpCommand = Get-Command -Name $PhpPath -ErrorAction Stop
$passwordOne = Read-Host 'Choose the initial administrator password (12+ characters)' -AsSecureString
$passwordTwo = Read-Host 'Enter the password again' -AsSecureString

if ($passwordOne.Length -lt 12) {
    throw 'Choose a password with at least 12 characters.'
}

$pointerOne = [IntPtr]::Zero
$pointerTwo = [IntPtr]::Zero

try {
    $pointerOne = [Runtime.InteropServices.Marshal]::SecureStringToBSTR($passwordOne)
    $pointerTwo = [Runtime.InteropServices.Marshal]::SecureStringToBSTR($passwordTwo)
    $plainOne = [Runtime.InteropServices.Marshal]::PtrToStringBSTR($pointerOne)
    $plainTwo = [Runtime.InteropServices.Marshal]::PtrToStringBSTR($pointerTwo)

    if ($plainOne -cne $plainTwo) {
        throw 'The passwords did not match.'
    }

    # Pass the secret only to this child process; never write it to a file.
    $env:CVA_ADMIN_PASSWORD = $plainOne
    & $phpCommand.Source (Join-Path $PSScriptRoot 'create-admin.php')
    if ($LASTEXITCODE -ne 0) {
        throw 'The PHP setup command did not complete successfully.'
    }
}
finally {
    Remove-Item Env:CVA_ADMIN_PASSWORD -ErrorAction SilentlyContinue
    if ($pointerOne -ne [IntPtr]::Zero) {
        [Runtime.InteropServices.Marshal]::ZeroFreeBSTR($pointerOne)
    }
    if ($pointerTwo -ne [IntPtr]::Zero) {
        [Runtime.InteropServices.Marshal]::ZeroFreeBSTR($pointerTwo)
    }
    $plainOne = $null
    $plainTwo = $null
}
