# WordPress.org release

The standalone `georg-kloster-prihod` theme is prepared for its first stable
public release as version 1.0.0. The release package contains the theme only;
Parish, Connect Hub, Calendar Workshop, Orthodox Donations and Slavonic
Translator remain independent optional plugins.

## Release checks

- Package source and metadata use version 1.0.0.
- PHP lint, isolated tests, translation checks and package audit passed in the
  repository workflow.
- The owner reports that visual review on the WordPress site passed.
- Theme Check and WordPress.org submission are external release steps and are
  not performed by the package build.

Build the theme ZIP from the repository root:

```sh
python scripts/package.py --only theme
```

The resulting archive is `build/georg-kloster-prihod-1.0.0.zip`.

## Submission details

- Theme slug: `georg-kloster-prihod`
- Source: https://github.com/VAtapin/Orthodox/tree/main/georg-kloster-prihod
- Author: Atapin Vladimir (`atapin`)
- Website: https://georg-kloster.ru/
- Contact: atapin@gmail.com

Submit the ZIP through the WordPress.org theme review process. Keep the source
repository's Git history as development provenance; the public theme changelog
starts at 1.0.0.
