package cmd

import (
	"fmt"

	"github.com/spf13/cobra"
)

// Version information
const (
	Version = "1.680"
	BuildID = "93708878506b95312f25ee706f2375d1cede8a90"
)

var versionCmd = &cobra.Command{
	Use:     "version",
	Aliases: []string{"v"},
	Short:   "Print Admini version",
	Long:    "Print Admini version information",
	Run: func(cmd *cobra.Command, args []string) {
		fmt.Printf("Admini %s %s\n", Version, BuildID)
	},
}

var infoCmd = &cobra.Command{
	Use:     "info",
	Aliases: []string{"o"},
	Short:   "Print binary compile info",
	Long:    "Print binary compile information",
	Run: func(cmd *cobra.Command, args []string) {
		fmt.Printf("Admini %s\n", Version)
		fmt.Printf("Build ID: %s\n", BuildID)
		fmt.Printf("Compiled with: Go (rebuilt from binary analysis)\n")
		fmt.Printf("OS: Linux\n")
		fmt.Printf("Architecture: x86_64\n")
	},
}