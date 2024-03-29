#!/bin/bash

## BASH COLORS
RED='\033[0;31m'
GREEN='\033[0;32m'
NC='\033[0m'

## DEFAULT VOLUME EXPORT from original Meilisearch Dockerfile:
# see Dockerfile in desired version https://github.com/docker-meilisearch/docker-meilisearch/blob/abb53a7/8.5/Dockerfile
DEFAULT_IMAGE_VOLUME_EXPORT_PATH="/var/meilisearch"

## Local docker things.
LOCAL_VOLUME_PATH=${HOME}"/meilisearchcivolume"
LOCAL_VOLUME_NAME="meilisearchci-volume"
LOCAL_IMAGE_NAME="meilisearchci-image:latest"
LOCAL_CONTAINER_NAME="meilisearchci-container"

## In Schema configuration available meilisearch cores
AVAILABLE_CORES=(
  "core_de"
  "core_en"
  "core_ar"
  "core_hy"
  "core_eu"
  "core_ptbr"
  "core_my"
  "core_ca"
  "core_zh"
  "core_cs"
  "core_da"
  "core_nl"
  "core_fi"
  "core_fr"
  "core_gl"
  "core_el"
  "core_hi"
  "core_hu"
  "core_id"
  "core_it"
  "core_ja"
  "core_km"
  "core_ko"
  "core_lo"
  "core_no"
  "core_fa"
  "core_pl"
  "core_pt"
  "core_ro"
  "core_ru"
  "core_es"
  "core_sv"
  "core_th"
  "core_tr"
  "core_uk"
  "core_rs"
  "core_ie"
  "core_lv"
)

prettyPrintOrExitOnError ()
{
  local output=${2}
  # shellcheck disable=SC2015
  [ "${1}" -eq 0 ] && echo -en "${GREEN}"' ✔\n'"${NC}" || { echo -en "${RED}"' ✘\n'"${NC}" "${output[@]}"; exit 1; }
}

assertDockerVersionIsGtOrEq193 ()
{
  echo -n "Check Docker version is >= 19.03"
  local DOCKER_VERSION
  DOCKER_VERSION=$(docker version -f "{{.Server.Version}}")
  local DOCKER_VERSION_MAJOR
  DOCKER_VERSION_MAJOR=$(echo "$DOCKER_VERSION"| cut -d'.' -f 1)
  local DOCKER_VERSION_MINOR
  DOCKER_VERSION_MINOR=$(echo "$DOCKER_VERSION"| cut -d'.' -f 2)

  if { [ "${DOCKER_VERSION_MAJOR}" -eq 19 ] && [ "${DOCKER_VERSION_MINOR}" -ge 3 ]; } || [ "${DOCKER_VERSION_MAJOR}" -gt 19 ]; then
      prettyPrintOrExitOnError 0
  else
    echo -en "${RED}"' ✘\n'"${NC}"
    echo -e "${RED}"'Docker version less than 19.0.3 can not continue'"${NC}"
    exit 1
  fi
}

isHTTP200 ()
{
  response=$(curl --write-out %\{http_code\} --silent --output /dev/null "${1}")
  if [ "$response" -eq "200" ] ; then
     return 0
  fi

  return 1
}

isPathOwnedByMeilisearch ()
{
  local status=0
  for path in "$@"
  do
    pathOwner=$(sudo stat -c '%u' "$path")
    # shellcheck disable=SC2015
    [ "$pathOwner" == 8983 ] && echo -e '  '"${GREEN}"'✔'"${NC}" "$path" || { echo -e '  '"${RED}"'✘'"${NC}" "$path"; status=1; }
  done

  return $status;
}

run_container ()
{
  echo -n "Creating testvolume"
  prettyPrintOrExitOnError $? "$(mkdir -p "$LOCAL_VOLUME_PATH" 2>&1)"

  echo -n "Add permissions to meilisearch group"
  prettyPrintOrExitOnError $? "$(sudo chmod g+w "$LOCAL_VOLUME_PATH" 2>&1)"

  echo -n "Changing group of volume to meilisearch user"
  prettyPrintOrExitOnError $? "$(sudo chown 8983:8983 "$LOCAL_VOLUME_PATH" 2>&1)"

  echo -n "Create named volume inside of ~/meilisearchcivolume"
  prettyPrintOrExitOnError $? "$(docker volume create --name "$LOCAL_VOLUME_NAME" --opt type=none --opt device="$LOCAL_VOLUME_PATH" --opt o=bind 2>&1)"

  echo -n "Starting container"
  prettyPrintOrExitOnError $? "$(docker run --name="$LOCAL_CONTAINER_NAME" -d -p 127.0.0.1:8998:8983 -v "$LOCAL_VOLUME_NAME":"$DEFAULT_IMAGE_VOLUME_EXPORT_PATH" "$LOCAL_IMAGE_NAME" 2>&1)"
}

cleanUp ()
{
  echo "Clean up the artifacts"

  echo -n "  stop container $LOCAL_CONTAINER_NAME"
  prettyPrintOrExitOnError $? "$(docker stop "$LOCAL_CONTAINER_NAME" 2>&1)"

  echo -n "  remove container $LOCAL_CONTAINER_NAME"
  prettyPrintOrExitOnError $? "$(docker container rm "$LOCAL_CONTAINER_NAME" 2>&1)"

  echo -n "  remove volume $LOCAL_VOLUME_NAME"
  prettyPrintOrExitOnError $? "$(docker volume rm "$LOCAL_VOLUME_NAME" 2>&1)"

  echo -n "  remove image $LOCAL_IMAGE_NAME"
  prettyPrintOrExitOnError $? "$(docker image rm "$LOCAL_IMAGE_NAME" 2>&1)"

  echo -n "  remove \"$LOCAL_VOLUME_PATH\" directory"
  prettyPrintOrExitOnError $? "$(sudo rm -Rf "$LOCAL_VOLUME_PATH" 2>&1)"
  # clean stdout
  echo
}

isCoreAvailable ()
{
  isHTTP200 "http://localhost:8998/meilisearch/${1}/select" || { return 1; }
  isHTTP200 "http://localhost:8998/meilisearch/${1}/mlt?q=*" || { return 1; }
  return 0;
}

isCoreUnavailable ()
{
  if ! isCoreAvailable $1; then
    return 0;
  fi
  return 1;
}

pingCore ()
{
  # shellcheck disable=SC2015
  isHTTP200 "http://localhost:8998/meilisearch/${1}/admin/ping" && { return 0; } || { return 1; }
}

getExpandedListOfPathsAsSudo ()
{
  if [[ $EUID -ne 0 ]] ; then
    echo "Function is unusable as non root user, please call function as root."
    return 1
  fi

  local paths=(
    "${1}"/data
    "${1}"/data/data
    "${1}"/data/configsets/ext_meilisearch_*/conf/
    "${1}"/data/configsets/*/conf/_schema_analysis*.json
  )
  echo "${paths[@]}"
}

assertVolumeExportHasNotBeenChanged ()
{
  echo -n "Check Dockerfile's VOLUME definition has not been changed."
  local EXPORTED_VOLUME
  EXPORTED_VOLUME=$(docker image inspect --format='{{ range $a, $b := .Config.Volumes }}{{ printf "%s " $a }}{{end}}' $LOCAL_IMAGE_NAME)
  if [[ "$EXPORTED_VOLUME" == "$DEFAULT_IMAGE_VOLUME_EXPORT_PATH " ]]; then
    prettyPrintOrExitOnError 0;
  else
    prettyPrintOrExitOnError 1 "${RED}"'\n  The VOLUME definition of image has been changed to "'"$EXPORTED_VOLUME"'".\n\n"'"${NC}"
  fi
}

assertDataPathIsCreatedByMeilisearch ()
{
  local DATA_PATH
  DATA_PATH="$LOCAL_VOLUME_PATH""/data/data"
  echo -en "\nWaiting for data directory: ""$DATA_PATH"
  while true ; do
    ((iteration++))
    # wait 10 seconds(80 times 0.125s)
    if [[ $iteration -gt 80 ]] ; then
      echo -ne "${RED}"'\nTimeout by awaiting of data directory.\nMeilisearch would normally have to do this.\n\n'"${NC}"
      cleanUp
      exit 1;
    fi

    if sudo test -d "$DATA_PATH" ; then
      prettyPrintOrExitOnError 0;
      return 0
    fi

    sleep 0.125
  done
}

assertAllCoresAreUp ()
{
  echo -e "\nWaiting for cores:"

  local cores=("${AVAILABLE_CORES[@]}")
  local iteration=0
  while true ; do
    ((iteration++))
    if [[ $iteration -gt 30 ]] ; then
      echo -ne "${RED}"'\nTimeout by pinging the cores.\n\n'"${NC}"
      cleanUp
      exit 1;
    fi

    for key in "${!cores[@]}" ; do

      if ! pingCore "${cores[$key]}";
      then
        echo -en "  ""${cores[$key]}"
        unset 'cores[key]'
        prettyPrintOrExitOnError 0
      fi
    done

    if [ "${#cores[@]}" -eq 0 ] ; then
      return 0
    fi

    sleep 1
  done
}

assertAllCoresAreQueriable ()
{
  echo -e "\nCheck all cores are queriable:"
  for core in "${AVAILABLE_CORES[@]}"
  do
    echo -n "  $core"
    prettyPrintOrExitOnError $? "$(isCoreAvailable "$core" 2>&1)"
  done
}

assertNecessaryPathsAreOwnedByMeilisearch ()
{
  echo -e "\nCheck paths are owned by meilisearch(8983):"
  local paths
  # shellcheck disable=SC2207
  paths=($(sudo /bin/bash -c "$(declare -f getExpandedListOfPathsAsSudo); getExpandedListOfPathsAsSudo $LOCAL_VOLUME_PATH"))

  isPathOwnedByMeilisearch "${paths[@]}" || { echo -e "${RED}"'\nThe image has files, which are not owned by meilisearch(8983) user.\n Please fix this issue.'"${NC}"; cleanUp; exit 1; }
}

assertCoresAreSwitchableViaEnvVar ()
{
  echo -e "\nCheck all cores are disabled except desired by \$TYPO3_MEILISEARCH_ENABLED_CORES env:"
  echo -n "  stop container $LOCAL_CONTAINER_NAME" prettyPrintOrExitOnError $? "$(docker stop "$LOCAL_CONTAINER_NAME" 2>&1)"

  echo -n "Starting container"
  prettyPrintOrExitOnError $? "$(docker run --env TYPO3_MEILISEARCH_ENABLED_CORES='german english danish' --name="$LOCAL_CONTAINER_NAME" -d -p 127.0.0.1:8998:8983 -v "$LOCAL_VOLUME_NAME":"$DEFAULT_IMAGE_VOLUME_EXPORT_PATH" "$LOCAL_IMAGE_NAME" 2>&1)"

  ENABLED_CORES=(
    "core_de"
    "core_en"
    "core_da"
  )

  SOME_DISABLED_CORES=(
    "core_fi"
    "core_fr"
    "core_gl"
    "core_el"
    "core_hi"
    "core_hu"
    "core_id"
    "core_it"
    "core_ja"
  )

  echo -e "\nCheck enabled cores are available:"
  for core in "${ENABLED_CORES[@]}"
  do
    echo -n "  $core is enabled"
    prettyPrintOrExitOnError $? "$(isCoreAvailable "$core" 2>&1)"
  done

  echo -e "\nCheck few other cores are really disabled:"
  for core in "${SOME_DISABLED_CORES[@]}"
  do
    echo -n "  $core is disabled"
    prettyPrintOrExitOnError $? "$(isCoreUnavailable "$core" 2>&1)"
  done

}

### run the tests

assertDockerVersionIsGtOrEq193

assertVolumeExportHasNotBeenChanged

run_container
assertAllCoresAreUp

assertAllCoresAreQueriable

assertDataPathIsCreatedByMeilisearch
assertNecessaryPathsAreOwnedByMeilisearch

assertCoresAreSwitchableViaEnvVar


echo -e "${GREEN}"'\nAll checks passed successfully!\n'"${NC}"

exit 0
