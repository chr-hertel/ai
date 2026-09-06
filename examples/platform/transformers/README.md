# Troubleshooting

## FFI

For running this examples, you need to enable the FFI extension in your `php.ini` file.
You can find a guide in the official documentation: https://transformers.codewithkyrian.com/getting-started

## Matlib Version

If you're having trouble running the transformers examples with the error:

```bash
Uncaught LogicException: matlib 1.0.1 is an unsupported version. Supported versions are greater than or equal to 1.1.0 and less than 2.0.0.
```

Please have a look at this GitHub issue: https://github.com/CodeWithKyrian/transformers-php/issues/88
