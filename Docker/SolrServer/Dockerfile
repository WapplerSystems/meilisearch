FROM meilisearch:9.3.0
MAINTAINER dkd Internet Service GmbH <meilisearch-eb-support@dkd.de>
ENV TERM linux

ARG MEILISEARCH_UNIX_UID="8983"
ARG MEILISEARCH_UNIX_GID="8983"

USER root
RUN rm -fR /opt/meilisearch/server/meilisearch/* \
  && usermod --non-unique --uid "${MEILISEARCH_UNIX_UID}" meilisearch \
  && groupmod --non-unique --gid "${MEILISEARCH_UNIX_GID}" meilisearch \
  && chown -R meilisearch:meilisearch /var/meilisearch /opt/meilisearch \
  && apt update && apt upgrade -y && apt install sudo -y \
  && echo "meilisearch ALL=NOPASSWD: /docker-entrypoint-initdb.d/as-sudo/*" > /etc/sudoers.d/meilisearch \
  && echo "# EXT:meilisearch relevant changes: " >> /etc/default/meilisearch.in.sh \
    && echo "MEILISEARCH_ENABLE_REMOTE_STREAMING=true" >> /etc/default/meilisearch.in.sh \
    && echo "MEILISEARCH_ENABLE_STREAM_BODY=true" >> /etc/default/meilisearch.in.sh \
    && echo "# END: EXT:meilisearch" >> /etc/default/meilisearch.in.sh

COPY Docker/MeilisearchServer/docker-entrypoint-initdb.d/ /docker-entrypoint-initdb.d
USER meilisearch

COPY --chown=meilisearch:meilisearch Resources/Private/Meilisearch/ /var/meilisearch/data
RUN mkdir -p /var/meilisearch/data/data
