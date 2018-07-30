#!/bin/bash

if [ "${HLE_INTERVAL}" == "" ]; then
    HLE_INTERVAL=300
fi

php /usr/share/hle/hle-renew.php

echo "Sleep ${HLE_INTERVAL} sec ..."
sleep ${HLE_INTERVAL}
