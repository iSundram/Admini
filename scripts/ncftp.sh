#!/bin/sh

VERSION=3.2.6
PACKAGES_DIR=/usr/local/directadmin/scripts/packages
TAR_FILE="${PACKAGES_DIR}/ncftp-${VERSION}-src.tar.gz"
SRC_DIR="${PACKAGES_DIR}/ncftp-${VERSION}"

mkdir -p "${PACKAGES_DIR}"

if ! curl --fail --location --output "${TAR_FILE}" https://files.directadmin.com/services/ncftp-${VERSION}-src.tar.gz; then
	echo "Unable to download the ncftp-${VERSION}"
	exit 1
fi

tar xzf "${TAR_FILE}" -C "${PACKAGES_DIR}"

(
	cd "${SRC_DIR}" || exit 1

	sed -i 's/^Bookmark/extern Bookmark/' sh_util/gpshare.c
	./configure --prefix=/usr
	make
	make install
)
rm -rf "${SRC_DIR:?}"