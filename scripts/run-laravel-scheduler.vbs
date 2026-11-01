Option Explicit

Dim shell, filesystem, projectRoot, phpPath, artisanPath, command, exitCode
Set shell = CreateObject("WScript.Shell")
Set filesystem = CreateObject("Scripting.FileSystemObject")

If WScript.Arguments.Count < 1 Then
    WScript.Quit 2
End If

projectRoot = filesystem.GetParentFolderName(filesystem.GetParentFolderName(WScript.ScriptFullName))
phpPath = WScript.Arguments(0)
artisanPath = filesystem.BuildPath(projectRoot, "artisan")
command = Chr(34) & phpPath & Chr(34) & " " & Chr(34) & artisanPath & Chr(34) & " schedule:run"

shell.CurrentDirectory = projectRoot
exitCode = shell.Run(command, 0, True)
WScript.Quit exitCode
