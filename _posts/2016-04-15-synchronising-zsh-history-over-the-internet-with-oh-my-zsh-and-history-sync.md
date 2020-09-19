---
layout: post
title: Synchronising Zsh history over the Internet with Oh-My-Zsh and history-sync
categories: [zsh_history, oh-my-zsh, plugin]
---

_"Why doesn't Oh-My-Zsh have a plugin that allows me to synchronise my Zsh history over the Internet?"_

In the project's 200+ plugin base, not one plugin achieves said functionality, and that's why I wrote [history-sync](https://github.com/wulfgarpro/history-sync).

The basic concept is:

1. Encrypt $HOME/.zsh_history using GPG public key encryption on the source computer
2. Push encrypted zsh_history to a remote Git repository
3. Pull encrypted zsh_history from said Git repository and decrypt on the destination computer
4. Merge decrypted zsh\_history with $HOME/.zsh_history

All of this is achievable using history-sync's alias commands:

* `zhps -r <string> -r <string> -r ...`
* `zhpl`
* `zhsync`

See the project's [README.md](https://github.com/wulfgarpro/history-sync/blob/master/README.md) for more detail.
