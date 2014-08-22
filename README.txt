This add-on is intended to export a complete Acquia Search Index using command line tools

To check 1 specific index
./acquia-search-service-export export -p "../resources/private.key" -c "useast1balc10" -i "MUVW-19050"

To check 1 specific colony
./acquia-search-service-export export -p "../resources/private.key" -c "useast1balc10"

To check everything
./acquia-search-service-export export -p "../resources/private.key"

Example

acquia-search-service-audit/bin$ ./audit.php audit -p "../resources/private.key" -c "useast1balc10" -i "MUVW-19050"
Repository found for colony us-east-1-c10 (Acquia QA). Using useast1bal@svn-3.search-service.hosting.acquia.com:useast1bal.git with branch c10
Checking out branch c10
Getting latest changes
No errors found
