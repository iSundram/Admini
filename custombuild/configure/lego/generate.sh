#!/bin/bash

version=$(lego -v | cut -d ' ' -f 3)

echo "{"
for p in $(lego dnshelp | grep '^  [^$]' | tr -d ','); do
	if [ "${p}" = "exec" ]; then
		continue
	fi
	blob=$(lego dnshelp -c "${p}")
	name=$(sed -n 's/^Configuration for \(.*\)\.$/\1/p' <<< "${blob}")
	code=$(sed -n 's/^Code:\s*'"'"'\(.*\)'"'"'$/\1/p' <<< "${blob}")
	creds=$(sed -n '/^Credentials:/,/^$/p' <<< "${blob//\"/}" | tail -n +2 | head -n -1 | sed -n 's/^  - \([^ ]*\):\s*\(.*\)$/\t\t\t"\1": "\2",/p')
	conf=$(sed -n '/^Additional Configuration:/,/^$/p' <<< "${blob//\"/}" | tail -n +2 | head -n -1 | sed -n 's/^  - \([^ ]*\):\s*\(.*\)$/\t\t\t"\1": "\2",/p')


	printf '\t"%s": {\n\t\t"name": "%s",\n\t\t"credentials": {\n%s\n\t\t},\n\t\t"additional_configuration": {\n%s\n\t\t}\n\t},\n' "${code}" "${name}" "${creds%,}" "${conf%,}"
done
printf '\t"version": "%s"\n' "${version}"
echo "}"
