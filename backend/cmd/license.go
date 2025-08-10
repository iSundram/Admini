package cmd

import (
	"fmt"

	"directadmin/pkg/license"
	"github.com/spf13/cobra"
)

var licenseCmd = &cobra.Command{
	Use:     "license",
	Aliases: []string{"l"},
	Short:   "Print license info",
	Long:    "Print DirectAdmin license information",
	Run: func(cmd *cobra.Command, args []string) {
		lic := license.GetLicense()
		lic.Print()
	},
}

var licenseSetCmd = &cobra.Command{
	Use:   "license-set [license-key]",
	Short: "Change license key",
	Long:  "Change the DirectAdmin license key",
	Args:  cobra.ExactArgs(1),
	Run: func(cmd *cobra.Command, args []string) {
		err := license.SetLicense(args[0])
		if err != nil {
			fmt.Printf("Failed to set license: %v\n", err)
			return
		}
		fmt.Println("License key updated successfully")
	},
}