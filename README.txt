This add-on is intended to export a complete Acquia Search Index using command line tools

To export all Indexes. The command will ask and store your Acquia Network credentials
./acquia-search-service-export export

To export 1 specific index
./acquia-search-service-export export --index ABCD-1234

To export to a specific directory
./acquia-search-service-export export --path "/tmp/acquia_search_export"

Example

    ./acquia-search-service-export export --index ILMV-27747
    [info] Checking if the given subscription has Acquia Search indexes...
    [info] Found 9 Acquia Search indexes.
    [info] Exporting all documents for index ILMV-27747.
    [info] Found 200 documents. Exporting...
    [info] Exported 200 documents. Checking for more documents
    [info] Found 200 documents. Exporting...
    ...
    [info] Exported 128 documents. Checking for more documents
    [info] Exported 4728 documents. Finished export for ILMV-27747.

    ./acquia-search-service-export export --index ILMV-27747 --path "/mnt/tmp/somethingnonexisting"
    [info] Checking if the given subscription has Acquia Search indexes...
    [info] Found 9 Acquia Search indexes.

