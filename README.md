# vendors-graphs

[![Build Status](https://github.com/innmind/vendors-graphs/workflows/CI/badge.svg?branch=main)](https://github.com/innmind/vendors-graphs/actions?query=workflow%3ACI)
[![codecov](https://codecov.io/gh/innmind/vendors-graphs/branch/develop/graph/badge.svg)](https://codecov.io/gh/innmind/vendors-graphs)
[![Type Coverage](https://shepherd.dev/github/innmind/vendors-graphs/coverage.svg)](https://shepherd.dev/github/innmind/vendors-graphs)

Description

## Installation

```sh
composer require innmind/vendors-graphs
```

## Usage

Todo

## Known issues

When this app is deployed on a server and exposed with the built-in http server, the pages are cut in the middle of SVGs. Even though the server sends the complete html content, the browser doesn't display it.

For some reason when the svg is accessed via a `img` tag (so as a dedicated http call), then the whole content is rendered correctly.

The problem with this approach is that now the links in SVGs no longer work.
