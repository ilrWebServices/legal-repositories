# Notes on import data

This CSV files in this directory originally needed some cleanup.

They were originally comma-delimited with a single quote (') as the _string delimiter_. Some of the text fields _also_ contained single quotes, so the files did not parse correctly.

Despite that, LibreOffice is able to import the files anyway, generally detecting a string delimiter quote vs. an actual quote in a text field. It failed on text fields with a single quote followed by a new line. E.g.:

```
bla bla attorneys'
fees
```

To clean up `consentDecree.csv` we found two instances of the above with a regular expression:

```
[^']'\n[^']
```

We manually removed the new line like so:

```
bla bla attorneys' fees
```

Once imported into LibreOffice, the data was re-exported to use a comma delimiter but a _double quote_ string delimiter, since that is the format the importer expects.

The `downloadVersion.csv` file was simply opened and re-exported as above.
