Anonymizer
==========
Anonymizer is a toolkit to help you to automate production data anonymization for test environments and compliancy.

## Features:

* Scramble data by column
* Maintains referential integrity
* Consistent output state on multiple runs of same source data
* Truncate entire tables
* Drop tables or individual columns
* Store configuration (DSN + Filename) in a .env file

## Usage

1. Scan your application's database schema and decide which columns contain sensitive information. For example: `user.email` or `request.ip`, etc.
2. Create a configuration file (example below) for your application that lists all the sensitive columns with a method for anonymization.
3. Run anonymizer on your test database:

    vendor/bin/anonymizer

## anonymizer.yml format

This file defines which columns needs to be anonymized, and using which method. Additionally you truncate or drop entire tables or columns.

Here's an example:

```yml
---
columns:
    user.email:
      method: faker
      arguments:
        formatter: email
      cascades:
        - user_email.address
        - comment.email

    request.ip:
      method: faker
      arguments:
        formatter: ipv5
      cascades:
        - exception.ip
truncate:
  - table1
  - table2

drop:
  - user.ip
  - request.agent
  - tmp*
  - *.password
```

All columns are listed in `tableName.columnName` format. For each column a `method` is defined, with some optional `arguments`. Most common is the `faker` method, that takes a `formatter` as an argument (i.e. email, userName, city, ipv4, etc - see the faker docs for more)

If you have any columns in other tables that reference this column, you can list them in the `cascades` key (optional). This will ensure that the external columns are updated with the same new value so their references still work.

## Wildcards

You can use wildcards (*, ?) in tablenames of the `drop` list.

## Configuration

You can use the environment (or a .env file) to pass ANONYMIZER_DSN (or PDO) and ANONYMIZER_FILENAME values to the `anonymizer run` command. These values will be used to connect to the database, and read the specified configuration yaml file.

Optionally: configure `ANONYMIZER_CONFIG_PATH` to point to a directory of `.conf` files containing database connection details. This will be registered as an INI Backend to the Connector service to resolve db connections from config files.

## About the "randomly" generated data

* The faker is initialized with the same seed every run (0). This ensures that multiple runs of anonymizer on the same source data result in the same anonymized data.
* The faker method ensures all generated values are unique within a single table. This prevents problems with references etc
* If you list cascades that contain values that are not defined in the source table, they will be updated to NULL. This prevents sensitive data lingering around in cascades accidentally. In a properly integrity-checked database this scenario would not happen.

## Verbosity / schema

anonymizer analyzes the schema (tablenames + columnnames) before running. This information is used by the wildcard functionality.
You can run anonymizer with `-v` to increase the verbosity to view this data. This can help you to verify if you're not seeing any tables or columns that should be dropped.

## License

MIT. Please refer to the [license file](LICENSE) for details.

## Brought to you by the LinkORB Engineering team

<img src="http://www.linkorb.com/d/meta/tier1/images/linkorbengineering-logo.png" width="200px" /><br />
Check out our other projects at [linkorb.com/engineering](http://www.linkorb.com/engineering).

Btw, we're hiring!
