package main

import (
	"fmt"
	"os"

	"directadmin/cmd"
)

// Version information - matches the original binary
const (
	Version = "1.680"
	BuildID = "93708878506b95312f25ee706f2375d1cede8a90"
)

func main() {
	if err := cmd.Execute(); err != nil {
		fmt.Fprintf(os.Stderr, "Error: %v\n", err)
		os.Exit(1)
	}
}