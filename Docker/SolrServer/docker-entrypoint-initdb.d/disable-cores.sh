#!/usr/bin/env bash

shopt -s extglob

if [ -n "${TYPO3_MEILISEARCH_ENABLED_CORES}" ]
then
  for CORE_TO_DISABLE in /var/meilisearch/data/cores/*/core.properties
  do
    mv "${CORE_TO_DISABLE}" "${CORE_TO_DISABLE}_disabled";
  done

  for CORE_to_ENABLE in ${TYPO3_MEILISEARCH_ENABLED_CORES}
  do
    echo "Enable core ${CORE_to_ENABLE}"
    mv "/var/meilisearch/data/cores/${CORE_to_ENABLE}/core.properties_disabled" "/var/meilisearch/data/cores/${CORE_to_ENABLE}/core.properties"
  done
fi
