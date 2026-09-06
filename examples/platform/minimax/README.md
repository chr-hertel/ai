# MiniMax Examples

[MiniMax](https://platform.minimax.io) exposes several modalities through a single platform bridge:
chat (with streaming and token usage), text-to-speech (synchronous and asynchronous), image
generation, music generation and video generation.

Set `MINI_MAX_API_KEY` in `examples/.env.local` before running the examples.

## Chat

```bash
php platform/minimax/chat.php
php platform/minimax/chat-as-stream.php
php platform/minimax/chat-with-token-usage.php
```

## Text-to-speech

Audio is returned as binary; pipe it to a player like [mpg123](https://www.mpg123.de/):

```bash
php platform/minimax/text-to-speech.php | mpg123 -
php platform/minimax/text-to-speech-async.php | mpg123 -
```

## Image, music and video

```bash
php platform/minimax/text-to-image.php > minimax-image.jpg
php platform/minimax/music.php | mpg123 -
php platform/minimax/text-to-video.php
```
