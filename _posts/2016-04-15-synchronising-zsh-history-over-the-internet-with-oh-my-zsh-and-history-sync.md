---
layout: post
title: Synchronising Zsh history over the Internet with Oh My Zsh and history-sync
date: 19 April, 2016
keywords: "oh-my-zsh, oh-my-zsh plugin, zsh_history, shell history, synchronising shell history, zsh, history-sync, james fraser, wulfgarpro, wulfgar.pro@gmail.com, github"
---

{{ page.title }}
================

<p class="meta">19 April 2016 - Melbourne</p>

Occasionally I think to myself:

_"What was the complex command sequence I ran on my laptop when I was using it 2 hours ago?"_

When this happens, I either wait until I'm in range of my laptop to check my shell history, or I have to remember to record the commands, usually by sending an email to myself. None of these solutions is beneficial when I'm in a jam.

That's why I thought to myself:

_"Why doesn't Oh-My-Zsh have such a plug-in, where I can synchronise my ZSH history over the Internet?"_

In the project's 200+ plug-in base, not one plug-in achieves said functionality. So, that's why I wrote [history-sync](https://github.com/wulfgarpro/history-sync).

The basic concept is:

1. Encrypt $HOME/.zsh_history using GPG public key encryption on the source computer
2. Push encrypted zsh_history to a remote Git repository
3. Pull encrypted zsh_history from said Git repository and decrypt on the destination computer
4. Merge decrypted zsh\_history with $HOME/.zsh_history

All of this is achievable using history-sync's alias commands:

* `zhps -r <string> -r <string> -r ...`
* `zhpl`
* `zhsync`

[Here's](https://asciinema.org/a/43575) an example of its use.
