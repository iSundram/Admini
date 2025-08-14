package cmd

import (
	"fmt"

	"admini/pkg/config"
	"github.com/spf13/cobra"
)

var configCmd = &cobra.Command{
	Use:     "config",
	Aliases: []string{"c"},
	Short:   "Print Admini config",
	Long:    "Print Admini configuration",
	Run: func(cmd *cobra.Command, args []string) {
		cfg := config.GetConfig()
		cfg.PrintAll()
	},
}

var configGetCmd = &cobra.Command{
	Use:   "config-get [key]",
	Short: "Get Admini config value",
	Long:  "Get a specific Admini configuration value",
	Args:  cobra.ExactArgs(1),
	Run: func(cmd *cobra.Command, args []string) {
		cfg := config.GetConfig()
		value := cfg.Get(args[0])
		if value != "" {
			fmt.Println(value)
		} else {
			fmt.Printf("Config key '%s' not found\n", args[0])
		}
	},
}

var configSetCmd = &cobra.Command{
	Use:   "config-set [key] [value]",
	Short: "Set Admini config value",
	Long:  "Set a Admini configuration value",
	Args:  cobra.ExactArgs(2),
	Run: func(cmd *cobra.Command, args []string) {
		cfg := config.GetConfig()
		err := cfg.Set(args[0], args[1])
		if err != nil {
			fmt.Printf("Error setting config: %v\n", err)
			return
		}
		fmt.Printf("Config '%s' set to '%s'\n", args[0], args[1])
	},
}